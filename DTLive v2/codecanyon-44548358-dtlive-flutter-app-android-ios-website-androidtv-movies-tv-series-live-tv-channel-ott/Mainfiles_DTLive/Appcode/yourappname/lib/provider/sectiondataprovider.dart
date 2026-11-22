import '../model/sectionbannermodel.dart';
import '../model/sectionlistmodel.dart' as section;
import '../model/successmodel.dart';
import '../utils/utils.dart';
import '../webservice/apiservices.dart';
import 'package:flutter/material.dart';

class SectionDataProvider extends ChangeNotifier {
  SectionBannerModel sectionBannerModel = SectionBannerModel();
  section.SectionListModel sectionListModel = section.SectionListModel();
  List<section.Result>? sectionList = [];
  SuccessModel successModel = SuccessModel();

  bool loadingBanner = false, loadingSection = false;
  bool isBannerHovered = false;
  int? cBannerIndex = 0, lastTabPosition;

  /* Post Pagination */
  bool loadMore = false;
  int? totalRows, totalPage, currentPage;
  bool? isMorePage;
  String? feedCursor;
  final Map<int, String?> sectionCursors = <int, String?>{};
  final Map<int, bool> sectionMore = <int, bool>{};
  final Set<int> sectionLoading = <int>{};
  final Set<int> sectionLoaded = <int>{};
  bool feedRefreshing = false;

  Future<void> getSectionBanner(dynamic typeId, isHomePage) async {
    cBannerIndex = 0;
    printLog("getSectionBanner typeId :==> $typeId");
    printLog("getSectionBanner isHomePage :==> $isHomePage");
    loadingBanner = true;
    sectionBannerModel = SectionBannerModel();
    try {
      sectionBannerModel = await ApiService().sectionBanner(typeId, isHomePage);
      printLog("getSectionBanner message :==> ${sectionBannerModel.message}");
    } on Exception catch (e) {
      printLog("getSectionBanner Exception :==> $e");
    } finally {
      loadingBanner = false;
      notifyListeners();
    }
  }

  Future<void> clearOldData() async {
    sectionBannerModel = SectionBannerModel();
    sectionList?.clear();
    sectionList = [];
    feedCursor = null;
    sectionCursors.clear();
    sectionMore.clear();
    sectionLoading.clear();
    sectionLoaded.clear();
    notifyListeners();
  }

  void setLoading(bool flagLoading) {
    loadingBanner = flagLoading;
    loadingSection = flagLoading;
    notifyListeners();
  }

  void setTabPosition(int position) {
    lastTabPosition = position;
    notifyListeners();
  }

  void setCurrentBanner(int index) {
    cBannerIndex = index;
    notifyListeners();
  }

  void setBannerHovered(bool value) {
    if (isBannerHovered == value) return;
    isBannerHovered = value;
    notifyListeners();
  }

  Future<void> getSectionList(dynamic typeId, isHomePage, pageNo) async {
    printLog("getSectionList typeId :======> $typeId");
    printLog("getSectionList isHomePage :==> $isHomePage");
    printLog("getSectionList pageNo :======> $pageNo");
    if (pageNo == 1) {
      sectionList?.clear();
      sectionList = [];
    }
    loadingSection = true;
    printLog("getSectionList length :======> ${sectionList?.length}");
    sectionListModel = section.SectionListModel();
    try {
      sectionListModel =
          await ApiService().sectionList(typeId, isHomePage, pageNo);
    } on Exception catch (e) {
      printLog("getSectionList Exception :==> $e");
      setLoadMore(false);
      loadingSection = false;
      loadMore = false;
      notifyListeners();
      return;
    }
    printLog("getSectionList message :==> ${sectionListModel.message}");
    printLog(
        "sectionListModel length :=1=> ${(sectionListModel.result?.length ?? 0)}");
    if (pageNo == 1) {
      sectionList?.clear();
      sectionList = [];
    }
    if (sectionListModel.status == 200) {
      setPagination(sectionListModel.totalRows, sectionListModel.totalPage,
          sectionListModel.currentPage, sectionListModel.morePage);
      if (sectionListModel.result != null &&
          (sectionListModel.result?.length ?? 0) > 0) {
        printLog(
            "sectionListModel length :=2=> ${(sectionListModel.result?.length ?? 0)}");
        for (var i = 0; i < (sectionListModel.result?.length ?? 0); i++) {
          sectionListModel.result?[i].scrollController = ScrollController();
          sectionList?.add(sectionListModel.result?[i] ?? section.Result());
        }
        final Map<String, section.Result> postMap = {};
        sectionList?.forEach((item) {
          final key =
              '${item.id}-${item.typeId}-${item.videoType}-${item.subVideoType}';
          postMap[key] = item;
        });
        sectionList = postMap.values.toList();
        setLoadMore(false);
        printLog(
            "sectionListModel length :=3=> ${(sectionListModel.result?.length ?? 0)}");
      } else {
        setLoadMore(false);
      }
    } else {
      setLoadMore(false);
    }
    loadingSection = false;
    notifyListeners();
  }

  /// Cursor-based feed for the home page. The numbered API above remains
  /// available to existing screens and older clients.
  Future<void> getFeedSections(
    dynamic typeId,
    dynamic isHomePage, {
    bool refresh = false,
  }) async {
    final isFirstLoad = sectionList == null || sectionList!.isEmpty;
    if (refresh) {
      feedCursor = null;
      sectionCursors.clear();
      sectionMore.clear();
      sectionLoading.clear();
      sectionLoaded.clear();
    }
    feedRefreshing = true;
    if (isFirstLoad) {
      loadingSection = true;
      notifyListeners();
    }
    try {
      final page = await ApiService().feedSections(
        typeId,
        isHomePage,
        cursor: refresh ? null : feedCursor,
      );
      final incoming = page.result ?? <section.Result>[];
      if (refresh || isFirstLoad) {
        sectionList = incoming;
      } else {
        final merged = <String, section.Result>{
          for (final item in sectionList ?? <section.Result>[]) _sectionKey(item): item,
        };
        for (final item in incoming) {
          merged[_sectionKey(item)] = item;
        }
        sectionList = merged.values.toList(growable: true);
      }
      feedCursor = page.nextCursor;
      isMorePage = page.hasMore ?? false;
      currentPage = (currentPage ?? 0) + 1;
      totalRows = null;
      totalPage = null;
      loadingSection = false;
      notifyListeners();

      // Individual rows fetch their bounded first page when they enter the
      // viewport. This keeps distant sections and cards out of the first load.
    } on Exception catch (e) {
      printLog('getFeedSections Exception :==> $e');
      loadingSection = false;
      loadMore = false;
    } finally {
      loadMore = false;
      feedRefreshing = false;
      notifyListeners();
    }
  }

  Future<void> getFeedSectionItems(
    section.Result sectionItem, {
    bool refresh = false,
  }) async {
    final sectionId = sectionItem.id ?? 0;
    if (sectionId <= 0) return;
    if (sectionLoading.contains(sectionId)) return;
    if (!refresh && sectionLoaded.contains(sectionId) &&
        !(sectionMore[sectionId] ?? false)) return;
    sectionLoading.add(sectionId);
    if (refresh) {
      sectionLoaded.remove(sectionId);
      sectionCursors.remove(sectionId);
    }
    try {
      final page = await ApiService().feedSectionItems(
        sectionId,
        cursor: refresh ? null : sectionCursors[sectionId],
        limit: (sectionItem.noOfContent ?? 12).clamp(4, 20).toInt(),
      );
      final merged = <String, section.Datum>{
        for (final item in refresh ? <section.Datum>[] : (sectionItem.data ?? <section.Datum>[])) _itemKey(item): item,
      };
      for (final item in page.result) {
        merged[_itemKey(item)] = item;
      }
      sectionItem.data = merged.values.toList(growable: true);
      sectionCursors[sectionId] = page.nextCursor;
      sectionMore[sectionId] = page.hasMore;
      sectionLoaded.add(sectionId);
    } on Exception catch (e) {
      printLog('getFeedSectionItems Exception :==> $e');
    } finally {
      sectionLoading.remove(sectionId);
      notifyListeners();
    }
  }

  bool hasLoadedFeedSection(int sectionId) => sectionLoaded.contains(sectionId);

  bool isLoadingFeedSection(int sectionId) => sectionLoading.contains(sectionId);

  String _sectionKey(section.Result item) =>
      '${item.id}-${item.typeId}-${item.videoType}-${item.subVideoType}';

  String _itemKey(section.Datum item) =>
      '${item.id}-${item.typeId}-${item.videoType}-${item.subVideoType}';

  void setLoadMore(bool loadMore) {
    printLog("setLoadMore loadMore :=> $loadMore");
    this.loadMore = loadMore;
    notifyListeners();
  }

  void setPagination(
      int? totalRows, int? totalPage, int? currentPage, bool? morePage) {
    printLog("setPagination currentPage :==> $currentPage");
    printLog("setPagination totalRows :====> $totalRows");
    printLog("setPagination totalPage :====> $totalPage");
    printLog("setPagination morePage :=====> $morePage");
    this.currentPage = currentPage;
    this.totalRows = totalRows;
    this.totalPage = totalPage;
    isMorePage = morePage;
    notifyListeners();
  }

  Future<void> setBookMark(BuildContext context, position) async {
    if ((sectionBannerModel.result?[position].isBookmark ?? 0) == 0) {
      sectionBannerModel.result?[position].isBookmark = 1;
      Utils.showSnackbar(context, "success", "addwatchlistmessage", true);
    } else {
      sectionBannerModel.result?[position].isBookmark = 0;
      Utils.showSnackbar(context, "success", "removewatchlistmessage", true);
    }
    notifyListeners();
    addRemoveBookmark(
      sectionBannerModel.result?[position].subVideoType ?? 0,
      sectionBannerModel.result?[position].videoType ?? 0,
      sectionBannerModel.result?[position].id,
    );
  }

  Future<void> addRemoveBookmark(
      dynamic subVideoType, videoType, videoId) async {
    printLog("addRemoveBookmark subVideoType :==> $subVideoType");
    printLog("addRemoveBookmark videoType :=====> $videoType");
    printLog("addRemoveBookmark videoId :=======> $videoId");
    successModel =
        await ApiService().addRemoveBookmark(subVideoType, videoType, videoId);
    printLog("addRemoveBookmark status :===> ${successModel.status}");
    printLog("addRemoveBookmark message :==> ${successModel.message}");
  }

  void clearProvider() {
    printLog("<================ clearProvider ================>");
    loadingBanner = false;
    loadingSection = false;
    loadMore = false;
    sectionBannerModel = SectionBannerModel();
    sectionListModel = section.SectionListModel();
    successModel = SuccessModel();
    cBannerIndex = 0;
    lastTabPosition = 0;
    totalRows = null;
    totalPage = null;
    currentPage = null;
    isMorePage = null;
    feedCursor = null;
    sectionCursors.clear();
    sectionMore.clear();
    sectionLoading.clear();
    sectionLoaded.clear();
    feedRefreshing = false;
  }
}
