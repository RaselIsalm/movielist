import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../ads/adsterra_ad.dart';

/// Keeps the content grid readable while using otherwise empty desktop space
/// for a single, non-blocking ad slot. The rail is intentionally disabled on
/// narrow screens so cards never get squeezed or covered by an ad.
class WebContentWithAdRail extends StatelessWidget {
  final Widget child;
  final bool enabled;

  const WebContentWithAdRail({
    required this.child,
    this.enabled = true,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final availableWidth = constraints.maxWidth;
        if (!enabled || availableWidth < 1180) {
          return SizedBox(width: double.infinity, child: child);
        }

        final contentWidth =
            math.min(1120.0, availableWidth - 324.0).toDouble();
        return Center(
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: contentWidth + 324.0),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(width: contentWidth, child: child),
                const SizedBox(width: 24),
                const Padding(
                  padding: EdgeInsets.only(top: 12),
                  child: AdsterraAd(unit: AdsterraUnit.banner300x250),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
