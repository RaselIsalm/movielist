import 'dart:async';

import 'package:http/http.dart' as http;

/// Bounded metadata/first-chunk prewarm for cards that are about to be watched.
/// Requests are deduplicated and capped so a long feed never creates one
/// player/controller per card.
class MediaPrewarmService {
  MediaPrewarmService._();

  static final Set<String> _completed = <String>{};
  static final Set<String> _inFlight = <String>{};
  static const int _maxConcurrent = 2;

  static Future<void> prewarm(String? url) async {
    final value = (url ?? '').trim();
    if (value.isEmpty || !value.startsWith(RegExp(r'https?://'))) return;
    if (_completed.contains(value) || _inFlight.contains(value)) return;
    if (_inFlight.length >= _maxConcurrent) return;
    _inFlight.add(value);
    try {
      final response = await http.get(
        Uri.parse(value),
        headers: const {'Range': 'bytes=0-262143', 'Accept': 'video/mp4'},
      );
      if (response.statusCode == 200 || response.statusCode == 206) {
        _completed.add(value);
      }
    } catch (_) {
      // Prewarm is opportunistic; the player remains the source of truth.
    } finally {
      _inFlight.remove(value);
    }
  }
}
