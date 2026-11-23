import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../analytics/first_party_telemetry.dart';
import 'adsterra_ad.dart';
import 'ad_policy.dart';

/// Shows a user-triggered, clearly labelled ad before opening a video.
///
/// This is a regular 300x250 banner inside an explicit dialog. It does not
/// auto-click, redirect, or open a new window. Native apps bypass it and keep
/// their existing ad flow.
Future<bool> showAdsterraPreplayAd(BuildContext context) async {
  if (!kIsWeb || !context.mounted) return true;

  if (!await AdPolicy.ensureConsent(context)) return false;
  if (!AdPolicy.tryClaimPreplay()) return true;

  final telemetry = FirstPartyTelemetry.instance;
  telemetry.track(
    'ad_impression',
    placement: 'preplay',
    adUnit: AdsterraUnit.banner300x250.name,
    consent: true,
  );

  try {
    final shouldContinue = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return Dialog(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 360),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Advertisement',
                      style: TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                  const SizedBox(height: 12),
                  const AdsterraAd(unit: AdsterraUnit.banner300x250),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: TextButton(
                      onPressed: () => Navigator.of(dialogContext).pop(true),
                      child: const Text('Close and continue'),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
    telemetry.track('ad_close', placement: 'preplay', consent: true);
    return shouldContinue == true;
  } finally {
    AdPolicy.releasePreplay();
  }
}
