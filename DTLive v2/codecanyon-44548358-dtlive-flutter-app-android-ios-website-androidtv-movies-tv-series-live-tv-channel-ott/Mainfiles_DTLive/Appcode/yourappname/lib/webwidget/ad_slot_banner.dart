import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../provider/adslotprovider.dart';

class AdSlotBanner extends StatefulWidget {
  final String slotKey;
  final EdgeInsetsGeometry margin;

  const AdSlotBanner({
    super.key,
    required this.slotKey,
    this.margin = const EdgeInsets.symmetric(vertical: 12),
  });

  @override
  State<AdSlotBanner> createState() => _AdSlotBannerState();
}

class _AdSlotBannerState extends State<AdSlotBanner> {
  bool _failed = false;

  @override
  void didUpdateWidget(covariant AdSlotBanner oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.slotKey != widget.slotKey) {
      _failed = false;
    }
  }

  @override
  void initState() {
    super.initState();
    // Loading is intentionally best-effort: an ad outage must never delay
    // the page content or leave a reserved blank slot.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        context.read<AdSlotProvider>().load();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final ads = context.watch<AdSlotProvider>().forSlot(widget.slotKey);
    if (_failed || ads.isEmpty) return const SizedBox.shrink();
    final ad = ads.first;
    if (ad.image.isEmpty) return const SizedBox.shrink();
    final wide = MediaQuery.sizeOf(context).width >= 700;
    return Padding(
      padding: widget.margin,
      child: Center(
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: wide ? 728 : 480),
          child: AspectRatio(
            aspectRatio: wide ? 728 / 90 : 320 / 50,
            child: Semantics(
              label: ad.altText,
              button: ad.clickUrl?.isNotEmpty == true,
              child: InkWell(
                onTap: ad.clickUrl?.isNotEmpty == true
                    ? () async {
                        final uri = Uri.tryParse(ad.clickUrl!);
                        if (uri != null && await canLaunchUrl(uri)) {
                          await launchUrl(
                            uri,
                            mode: kIsWeb
                                ? LaunchMode.externalApplication
                                : LaunchMode.platformDefault,
                          );
                        }
                      }
                    : null,
                child: Image.network(
                  ad.image,
                  fit: BoxFit.contain,
                  errorBuilder: (context, error, stackTrace) {
                    if (mounted && !_failed) {
                      WidgetsBinding.instance.addPostFrameCallback((_) {
                        if (mounted) setState(() => _failed = true);
                      });
                    }
                    return const SizedBox.shrink();
                  },
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
