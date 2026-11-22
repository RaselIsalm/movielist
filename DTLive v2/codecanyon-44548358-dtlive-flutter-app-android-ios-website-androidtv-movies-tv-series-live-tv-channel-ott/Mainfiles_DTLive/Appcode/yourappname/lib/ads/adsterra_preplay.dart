import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import 'adsterra_ad.dart';

/// Shows a user-triggered, clearly labelled ad before opening a video.
///
/// This is a regular 300x250 banner inside an explicit dialog. It does not
/// auto-click, redirect, or open a new window. Native apps bypass it and keep
/// their existing ad flow.
Future<bool> showAdsterraPreplayAd(BuildContext context) async {
  if (!kIsWeb || !context.mounted) return true;

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

  return shouldContinue == true;
}
