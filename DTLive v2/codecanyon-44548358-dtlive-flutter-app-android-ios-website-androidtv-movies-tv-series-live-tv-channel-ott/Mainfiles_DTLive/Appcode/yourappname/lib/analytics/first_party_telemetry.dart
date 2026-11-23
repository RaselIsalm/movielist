import 'dart:async';
import 'dart:math';

import 'package:flutter/foundation.dart';

import '../utils/constant.dart';
import '../webservice/dioclient.dart';

/// Small, privacy-safe event client for product and placement measurement.
/// Ad clicks remain the ad network's source of truth because Adsterra renders
/// inside a cross-origin iframe and must not be instrumented or modified.
class FirstPartyTelemetry {
  FirstPartyTelemetry._();

  static final FirstPartyTelemetry instance = FirstPartyTelemetry._();
  static const _allowedEvents = <String>{
    'page_view',
    'web_boot',
    'first_content',
    'player_ready',
    'video_start',
    'video_complete',
    'ad_impression',
    'ad_close',
    'ad_load_error',
    'player_error',
  };

  final List<Map<String, dynamic>> _queue = <Map<String, dynamic>>[];
  Timer? _flushTimer;
  late final String _sessionId =
      '${DateTime.now().microsecondsSinceEpoch}-${Random().nextInt(1 << 31)}';

  void track(
    String event, {
    String? placement,
    String? contentId,
    String? adUnit,
    String? viewport,
    bool? consent,
  }) {
    if (!kIsWeb) return;
    if (!_allowedEvents.contains(event)) return;
    _queue.add({
      'event': event,
      'session_id': _sessionId,
      if (placement != null) 'placement': placement,
      if (contentId != null && contentId.isNotEmpty) 'content_id': contentId,
      if (adUnit != null) 'ad_unit': adUnit,
      if (viewport != null) 'viewport': viewport,
      if (consent != null) 'consent': consent,
      'occurred_at': DateTime.now().toUtc().toIso8601String(),
    });
    if (_queue.length >= 8) {
      unawaited(flush());
    } else {
      _flushTimer ??= Timer(const Duration(seconds: 8), () {
        _flushTimer = null;
        unawaited(flush());
      });
    }
  }

  Future<void> flush() async {
    _flushTimer?.cancel();
    _flushTimer = null;
    if (_queue.isEmpty) return;
    final batch = List<Map<String, dynamic>>.from(_queue);
    _queue.clear();
    try {
      await DioClient.instance.dio.post(
        'telemetry/events',
        data: {
          'events': batch,
          'device_id': Constant.currentDeviceId,
        },
      );
    } catch (_) {
      // Telemetry must never block content. Drop a failed batch rather than
      // retrying indefinitely or retaining potentially sensitive data.
    }
  }
}
