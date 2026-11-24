import '../web_js/js_helper.dart';

/// Opens the publisher Smartlink from an explicit web playback action.
///
/// The mobile implementation of [JSHelper] is a no-op, so this helper is
/// safe to call from shared Flutter code while the Smartlink remains a Web
/// placement. Keeping the URL here also prevents individual content pages
/// from drifting out of sync.
class SmartlinkAd {
  SmartlinkAd._();

  static const String url =
      'https://directyp.org/4/99c930be151dca271e5708c15812c3b4';

  static final JSHelper _jsHelper = JSHelper();

  static Future<void> open() async {
    try {
      await _jsHelper.callOpenTab(url, '_blank');
    } catch (_) {
      // An ad blocker or popup policy must never stop playback.
    }
  }
}
