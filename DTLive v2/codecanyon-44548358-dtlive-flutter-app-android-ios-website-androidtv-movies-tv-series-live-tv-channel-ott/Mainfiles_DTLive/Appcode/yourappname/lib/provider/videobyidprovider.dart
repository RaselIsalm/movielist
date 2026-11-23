import '../model/castdetailmodel.dart';
import '../model/contentbyidmodel.dart' as content;
import '../utils/constant.dart';
import '../webservice/apiservices.dart';
import 'package:flutter/material.dart';
import '../utils/utils.dart';

class VideoByIDProvider extends ChangeNotifier {
  CastDetailModel castDetailModel = CastDetailModel();
  content.ContentByIdModel contentByIdModel = content.ContentByIdModel();
  List<content.Result>? contentList = [];

  bool loading = false;
  bool hasLoadError = false;
  String? currentCatId, currentLangId, currentChannelId, currentCastId;
  String? _currentContentFilterKey;
  int _contentRequestGeneration = 0;

  /* Post Pagination */
  bool loadMore = false;
  int? totalRows, totalPage, currentPage;
  bool? isMorePage;

  void setLoading(bool isLoading) {
    loading = isLoading;
    notifyListeners();
  }

  Future<void> getCastDetails(dynamic castID) async {
    loading = true;
    hasLoadError = false;
    notifyListeners();
    try {
      castDetailModel = await ApiService().getCastDetails(castID);
      printLog("getCastDetails status :==> ${castDetailModel.status}");
      hasLoadError = castDetailModel.status != 200;
    } on Exception catch (e) {
      hasLoadError = true;
      printLog("getCastDetails Exception :==> $e");
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> getVideoByCategory(dynamic categoryID, pageNo) async {
    _selectContentFilter('category', categoryID);
    notifyListeners();
    printLog("getVideoByCategory userID :======> ${Constant.userID}");
    printLog("getVideoByCategory categoryID :==> $categoryID");
    printLog("getVideoByCategory pageNo =======> $pageNo");
    await _loadContentPage(
      pageNo: pageNo,
      label: 'getVideoByCategory',
      request: () => ApiService().contentByCategory(categoryID, pageNo),
    );
  }

  Future<void> getVideoByLanguage(dynamic languageID, pageNo) async {
    _selectContentFilter('language', languageID);
    notifyListeners();
    printLog("getVideoByLanguage userID :======> ${Constant.userID}");
    printLog("getVideoByLanguage languageID :==> $languageID");
    printLog("getVideoByLanguage pageNo =======> $pageNo");
    await _loadContentPage(
      pageNo: pageNo,
      label: 'getVideoByLanguage',
      request: () => ApiService().contentByLanguage(languageID, pageNo),
    );
  }

  Future<void> getVideoByChannel(dynamic channelID, pageNo) async {
    _selectContentFilter('channel', channelID);
    notifyListeners();
    printLog("getVideoByChannel userID :=====> ${Constant.userID}");
    printLog("getVideoByChannel channelID :==> $channelID");
    printLog("getVideoByChannel pageNo ======> $pageNo");
    await _loadContentPage(
      pageNo: pageNo,
      label: 'getVideoByChannel',
      request: () => ApiService().contentByChannel(channelID, pageNo),
    );
  }

  Future<void> getVideoByCast(dynamic castID, pageNo) async {
    _selectContentFilter('cast', castID);
    notifyListeners();
    printLog("getVideoByCast userID :=====> ${Constant.userID}");
    printLog("getVideoByCast castID :=====> $castID");
    printLog("getVideoByCast pageNo ======> $pageNo");
    await _loadContentPage(
      pageNo: pageNo,
      label: 'getVideoByCast',
      request: () => ApiService().contentByCast(castID, pageNo),
    );
  }

  Future<void> _loadContentPage({
    required dynamic pageNo,
    required String label,
    required Future<content.ContentByIdModel> Function() request,
  }) async {
    final isFirstPage = pageNo == 1;
    final previousItems = List<content.Result>.from(
      contentList ?? <content.Result>[],
    );
    final previousModel = contentByIdModel;
    final requestGeneration = ++_contentRequestGeneration;
    loading = true;
    if (isFirstPage) hasLoadError = false;
    notifyListeners();

    try {
      final fetched = await request();
      if (requestGeneration != _contentRequestGeneration) return;
      contentByIdModel = fetched;
      printLog('$label result length :==> ${fetched.result?.length ?? 0}');
      if (fetched.status == 200) {
        hasLoadError = false;
        setPagination(
          fetched.totalRows,
          fetched.totalPage,
          fetched.currentPage,
          fetched.morePage,
        );
        final mergedItems = <content.Result>[
          if (!isFirstPage) ...previousItems,
          ...?fetched.result,
        ];
        final postMap = <String, content.Result>{};
        for (final item in mergedItems) {
          final key =
              '${item.id}-${item.typeId}-${item.videoType}-${item.subVideoType}';
          postMap[key] = item;
        }
        contentList = postMap.values.toList(growable: true);
      } else {
        hasLoadError = true;
        contentByIdModel = previousModel;
        contentList = previousItems;
      }
    } on Exception catch (e) {
      if (requestGeneration != _contentRequestGeneration) return;
      hasLoadError = true;
      contentByIdModel = previousModel;
      contentList = previousItems;
      printLog('$label Exception :==> $e');
    } finally {
      if (requestGeneration == _contentRequestGeneration) {
        loadMore = false;
        loading = false;
        notifyListeners();
      }
    }
  }

  void _selectContentFilter(String type, dynamic id) {
    final value = id.toString();
    final key = '$type:$value';
    if (_currentContentFilterKey != key) {
      _currentContentFilterKey = key;
      contentByIdModel = content.ContentByIdModel();
      contentList = [];
      totalRows = null;
      totalPage = null;
      currentPage = null;
      isMorePage = null;
      hasLoadError = false;
      _contentRequestGeneration++;
    }
    currentCatId = type == 'category' ? value : null;
    currentLangId = type == 'language' ? value : null;
    currentChannelId = type == 'channel' ? value : null;
    currentCastId = type == 'cast' ? value : null;
  }

  void setLoadMore(bool loadMore) {
    printLog("setLoadMore loadMore :=> $loadMore");
    this.loadMore = loadMore;
    notifyListeners();
  }

  void setPagination(
    int? totalRows,
    int? totalPage,
    int? currentPage,
    bool? morePage,
  ) {
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

  void clearProvider() {
    printLog("<================ clearProvider ================>");
    contentByIdModel = content.ContentByIdModel();
    castDetailModel = CastDetailModel();
    contentList = [];
    loading = false;
    hasLoadError = false;
    loadMore = false;
    totalRows = null;
    totalPage = null;
    currentPage = null;
    isMorePage = null;
    currentCatId = null;
    currentLangId = null;
    currentChannelId = null;
    currentCastId = null;
    _currentContentFilterKey = null;
    _contentRequestGeneration++;
  }
}
