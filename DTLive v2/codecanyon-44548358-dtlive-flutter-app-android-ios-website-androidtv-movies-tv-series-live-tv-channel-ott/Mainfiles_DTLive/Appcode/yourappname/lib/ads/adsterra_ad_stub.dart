import 'package:flutter/widgets.dart';

enum AdsterraUnit {
  banner300x250,
  banner320x50,
  nativeBanner,
}

/// Adsterra is a web-only placement. Native apps keep using the existing
/// AdMob placements and do not load third-party web scripts.
class AdsterraAd extends StatelessWidget {
  final AdsterraUnit unit;
  final EdgeInsetsGeometry margin;

  const AdsterraAd({
    super.key,
    required this.unit,
    this.margin = EdgeInsets.zero,
  });

  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}
