import 'package:flutter/foundation.dart';

import '../model/adslotmodel.dart';
import '../webservice/apiservices.dart';

class AdSlotProvider extends ChangeNotifier {
  final Map<String, List<AdCreative>> _slots = {};
  bool _loading = false;
  bool _loaded = false;

  List<AdCreative> forSlot(String key) =>
      List.unmodifiable(_slots[key] ?? const []);

  Future<void> load({bool refresh = false}) async {
    if (_loading || (_loaded && !refresh)) return;
    _loading = true;
    notifyListeners();
    try {
      final result = await ApiService().getAdSlots();
      _slots
        ..clear()
        ..addAll(result);
      _loaded = true;
    } catch (_) {
      // Ads are optional and must never block catalog paint.
    } finally {
      _loading = false;
      notifyListeners();
    }
  }
}
