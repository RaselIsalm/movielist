import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../model/genresmodel.dart';
import '../model/langaugemodel.dart';
import '../model/channelmodel.dart';
import '../model/sectiontypemodel.dart';
import '../utils/utils.dart';
import '../webservice/apiservices.dart';
import 'package:flutter/material.dart';

class HomeProvider extends ChangeNotifier {
  SectionTypeModel sectionTypeModel = SectionTypeModel();
  LangaugeModel langaugeModel = LangaugeModel();
  GenresModel genresModel = GenresModel();
  ChannelModel channelModel = ChannelModel();

  bool loading = false;
  int selectedIndex = -1;
  String currentPage = "";

  void notifyProvider() {
    notifyListeners();
  }

  static const String _sectionTypeCacheKey = 'dtlive_web_section_types_v1';

  Future<void> restoreCachedSectionTypes() async {
    if (!kIsWeb || sectionTypeModel.result?.isNotEmpty == true) return;
    try {
      final preferences = await SharedPreferences.getInstance();
      final raw = preferences.getString(_sectionTypeCacheKey);
      if (raw == null || raw.isEmpty) return;
      final payload = jsonDecode(raw);
      if (payload is! Map<String, dynamic>) return;
      final savedAt = DateTime.tryParse(payload['saved_at']?.toString() ?? '');
      if (savedAt == null || DateTime.now().difference(savedAt).inHours > 24) {
        return;
      }
      final data = payload['data'];
      if (data is! Map<String, dynamic>) return;
      final restored = SectionTypeModel.fromJson(data);
      if (restored.result?.isEmpty ?? true) return;
      sectionTypeModel = restored;
      loading = false;
      notifyListeners();
    } catch (_) {
      // A corrupted local snapshot must never block the network request.
    }
  }

  Future<void> getSectionType() async {
    final hasExistingData = sectionTypeModel.result?.isNotEmpty == true;
    if (!hasExistingData) {
      loading = true;
      notifyListeners();
    }
    try {
      final fetched = await ApiService().sectionType();
      if (fetched.status == 200 && fetched.result?.isNotEmpty == true) {
        sectionTypeModel = fetched;
      } else if (!hasExistingData) {
        sectionTypeModel = fetched;
      }
      if (kIsWeb && sectionTypeModel.result?.isNotEmpty == true) {
        final preferences = await SharedPreferences.getInstance();
        await preferences.setString(
          _sectionTypeCacheKey,
          jsonEncode({
            'saved_at': DateTime.now().toIso8601String(),
            'data': sectionTypeModel.toJson(),
          }),
        );
      }
    } on Exception catch (e) {
      printLog("getSectionType Exception :==> $e");
    }
    printLog("getSectionType status :==> ${sectionTypeModel.status}");
    printLog("getSectionType message :==> ${sectionTypeModel.message}");
    loading = false;
    notifyListeners();
  }

  Future<void> getGenres() async {
    try {
      genresModel = await ApiService().genres();
    } on Exception catch (e) {
      printLog("getGenres Exception :==> $e");
    }
    printLog("getGenres status :===> ${genresModel.status}");
    printLog("getGenres message :==> ${genresModel.message}");
    notifyListeners();
  }

  Future<void> getLanguage() async {
    try {
      langaugeModel = await ApiService().language();
    } on Exception catch (e) {
      printLog("getLanguage Exception :==> $e");
    }
    printLog("getLanguage status :===> ${langaugeModel.status}");
    printLog("getLanguage message :==> ${langaugeModel.message}");
    notifyListeners();
  }

  Future<void> getChannel() async {
    try {
      channelModel = await ApiService().channel();
    } on Exception catch (e) {
      printLog("getChannel Exception :==> $e");
    }
    printLog("getChannel status :===> ${channelModel.status}");
    printLog("getChannel message :==> ${channelModel.message}");
    notifyListeners();
  }

  Future<void> setLoading(bool isLoading) async {
    loading = isLoading;
    notifyListeners();
  }

  void setSelectedTab(int index) {
    selectedIndex = index;
    notifyListeners();
  }

  void setCurrentPage(String pageName) {
    currentPage = pageName;
    notifyListeners();
  }

  void homeNotifyProvider() {
    notifyListeners();
  }

  void clearProvider() {
    sectionTypeModel = SectionTypeModel();
    langaugeModel = LangaugeModel();
    genresModel = GenresModel();
    channelModel = ChannelModel();
    loading = false;
    selectedIndex = -1;
    currentPage = "";
  }
}
