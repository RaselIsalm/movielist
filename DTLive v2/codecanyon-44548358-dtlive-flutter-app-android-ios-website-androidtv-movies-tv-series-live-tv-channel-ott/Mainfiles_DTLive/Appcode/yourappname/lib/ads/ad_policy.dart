import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Central web advertising policy. Native platforms keep their existing ad
/// providers and never load the web-only Adsterra units.
class AdPolicy {
  static const Duration preplayCooldown = Duration(minutes: 20);
  static const String _consentKey = 'dtlive_ad_consent_v1';
  static const String _ageKey = 'dtlive_age_confirmed_v1';

  static DateTime? _lastPreplayAt;
  static bool _preplayInFlight = false;

  static bool get isWeb => kIsWeb;

  /// Returns true only after the user has accepted the web ad/content notice.
  /// The prompt is shown from an explicit Watch action, never on page load.
  static Future<bool> ensureConsent(BuildContext context) async {
    if (!kIsWeb || !context.mounted) return true;

    final preferences = await SharedPreferences.getInstance();
    final consentGiven = preferences.getBool(_consentKey) ?? false;
    final ageConfirmed = preferences.getBool(_ageKey) ?? false;
    if (consentGiven && ageConfirmed) return true;
    if (!context.mounted) return false;

    final accepted = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text('Before you continue'),
          content: const Text(
            'This site contains age-restricted content and clearly labelled '
            'advertising. Confirm that you are 18 or older and agree to the '
            'privacy notice to continue.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: const Text('Leave'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(true),
              child: const Text('I am 18+ and agree'),
            ),
          ],
        );
      },
    );

    if (accepted != true) return false;
    await preferences.setBool(_consentKey, true);
    await preferences.setBool(_ageKey, true);
    return true;
  }

  /// Claims the single preplay slot for the current tab/session.
  static bool tryClaimPreplay() {
    if (!kIsWeb || _preplayInFlight) return false;
    final now = DateTime.now();
    if (_lastPreplayAt != null &&
        now.difference(_lastPreplayAt!) < preplayCooldown) {
      return false;
    }
    _preplayInFlight = true;
    _lastPreplayAt = now;
    return true;
  }

  static void releasePreplay() {
    _preplayInFlight = false;
  }

  static bool shouldShowInterstitial() => kIsWeb && !_preplayInFlight;

  /// Used by list screens to keep ad density predictable and consistent.
  static bool isBannerBreak(int contentIndex) =>
      contentIndex > 0 && contentIndex % 6 == 0;

  static bool isNativeBreak(int contentIndex) =>
      contentIndex > 0 && contentIndex % 12 == 0;
}
