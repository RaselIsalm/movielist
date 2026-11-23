import '../model/sectiondetailmodel.dart';
import '../utils/constant.dart';
import '../webservice/apiservices.dart';
import 'package:flutter/material.dart';
import '../utils/utils.dart';

class SectionViewAllProvider extends ChangeNotifier {
  SectionDetailModel sectionDetailModel = SectionDetailModel();
  List<Result>? sectionDetailList = [];

  bool loading = false;
  bool hasLoadError = false;
  String? _currentSectionId;

  /* Post Pagination */
  bool loadMore = false;
  int? totalRows, totalPage, currentPage;
  bool? isMorePage;

  void setLoading(bool isLoading) {
    loading = isLoading;
    notifyListeners();
  }

  Future<void> getSectionDetails(dynamic sectionId, pageNo) async {
    printLog("getSectionDetails userID :=====> ${Constant.userID}");
    printLog("getSectionDetails sectionId :==> $sectionId");
    printLog("getSectionDetails pageNo :=====> $pageNo");

    final requestedSectionId = sectionId.toString();
    final isSameSection = _currentSectionId == requestedSectionId;
    final previousItems = isSameSection
        ? List<Result>.from(sectionDetailList ?? <Result>[])
        : <Result>[];
    final previousModel = isSameSection
        ? sectionDetailModel
        : SectionDetailModel();
    if (pageNo == 1 && !isSameSection) {
      _currentSectionId = requestedSectionId;
      sectionDetailModel = SectionDetailModel();
      sectionDetailList = [];
    }
    loading = true;
    if (pageNo == 1) hasLoadError = false;
    notifyListeners();
    try {
      sectionDetailModel = await ApiService().sectionDetails(sectionId, pageNo);
      if (sectionDetailModel.status == 200) {
        hasLoadError = false;
        setPagination(
          sectionDetailModel.totalRows,
          sectionDetailModel.totalPage,
          sectionDetailModel.currentPage,
          sectionDetailModel.morePage,
        );
        final mergedItems = <Result>[
          if (pageNo != 1) ...previousItems,
          ...?sectionDetailModel.result,
        ];
        if (mergedItems.isNotEmpty) {
          final postMap = <String, Result>{};
          for (final item in mergedItems) {
            final key =
                '${item.id}-${item.typeId}-${item.videoType}-${item.subVideoType}';
            postMap[key] = item;
          }
          sectionDetailList = postMap.values.toList(growable: true);
          setLoadMore(false);
          printLog(
            "sectionDetailModel length :=2=> ${(sectionDetailModel.result?.length ?? 0)}",
          );
        }
        printLog("getSectionDetails status :===> ${sectionDetailModel.status}");
        printLog(
          "getSectionDetails message :==> ${sectionDetailModel.message}",
        );
      } else {
        hasLoadError = true;
        sectionDetailModel = previousModel;
        sectionDetailList = previousItems;
      }
    } on Exception catch (e) {
      hasLoadError = true;
      sectionDetailModel = previousModel;
      sectionDetailList = previousItems;
      printLog("getSectionDetails Exception :==> $e");
    } finally {
      loading = false;
      loadMore = false;
      notifyListeners();
    }
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
    sectionDetailModel = SectionDetailModel();
    _currentSectionId = null;
    sectionDetailList?.clear();
    sectionDetailList = [];
    loading = false;
    hasLoadError = false;
    loadMore = false;
    totalRows;
    totalPage;
    currentPage;
    isMorePage;
  }
}
