import 'dart:ui_web' as ui_web;

import 'package:flutter/material.dart';
import 'package:universal_html/html.dart' as html;

enum AdsterraUnit {
  banner300x250,
  banner320x50,
  nativeBanner,
}

class AdsterraAd extends StatefulWidget {
  final AdsterraUnit unit;
  final EdgeInsetsGeometry margin;

  const AdsterraAd({
    super.key,
    required this.unit,
    this.margin = EdgeInsets.zero,
  });

  @override
  State<AdsterraAd> createState() => _AdsterraAdState();
}

class _AdsterraAdState extends State<AdsterraAd> {
  static int _nextViewId = 0;
  late final String _viewType;
  bool _collapsed = false;

  @override
  void initState() {
    super.initState();
    _viewType = 'adsterra-${widget.unit.name}-${_nextViewId++}';
    ui_web.platformViewRegistry.registerViewFactory(_viewType, (viewId) {
      final elementWidth = switch (widget.unit) {
        AdsterraUnit.banner300x250 => '300px',
        AdsterraUnit.banner320x50 => '320px',
        AdsterraUnit.nativeBanner => '100%',
      };
      final elementHeight = switch (widget.unit) {
        AdsterraUnit.banner300x250 => '250px',
        AdsterraUnit.banner320x50 => '50px',
        AdsterraUnit.nativeBanner => '280px',
      };
      final element = html.DivElement()
        ..style.width = elementWidth
        ..style.height = elementHeight
        ..style.display = 'flex'
        ..style.justifyContent = 'center'
        ..style.alignItems = 'center';
      _appendAdCode(element, widget.unit);
      return element;
    });
  }

  void _appendAdCode(html.DivElement root, AdsterraUnit unit) {
    switch (unit) {
      case AdsterraUnit.banner300x250:
        final options = html.ScriptElement()
          ..type = 'text/javascript'
          ..text = """
atOptions = {
  'key' : '5d4443051d962c9f88a7ace3df6696cc',
  'format' : 'iframe',
  'height' : 250,
  'width' : 300,
  'params' : {}
};
""";
        final script = html.ScriptElement()
          ..type = 'text/javascript'
          ..src =
              'https://bancadeltempoidea.org/22/5d4443051d962c9f88a7ace3df6696cc';
        script.onError.listen((_) => _collapseSlot(root));
        root.append(options);
        root.append(script);
      case AdsterraUnit.banner320x50:
        final options = html.ScriptElement()
          ..type = 'text/javascript'
          ..text = """
atOptions = {
  'key' : '6b272a10e55170223cad5f413212edbd',
  'format' : 'iframe',
  'height' : 50,
  'width' : 320,
  'params' : {}
};
""";
        final script = html.ScriptElement()
          ..type = 'text/javascript'
          ..src =
              'https://bancadeltempoidea.org/22/6b272a10e55170223cad5f413212edbd';
        script.onError.listen((_) => _collapseSlot(root));
        root.append(options);
        root.append(script);
      case AdsterraUnit.nativeBanner:
        final script = html.ScriptElement()
          ..async = true
          ..setAttribute('data-cfasync', 'false')
          ..src =
              'https://bancadeltempoidea.org/21/f41c27f0f037b0f4fd483e03a969b04a';
        script.onError.listen((_) => _collapseSlot(root));
        final container = html.DivElement()
          ..id = 'container-f41c27f0f037b0f4fd483e03a969b04a';
        root.append(script);
        root.append(container);
    }
  }

  void _collapseSlot(html.DivElement root) {
    root.style.height = '0';
    root.style.display = 'none';
    if (mounted) setState(() => _collapsed = true);
  }

  @override
  Widget build(BuildContext context) {
    final size = switch (widget.unit) {
      AdsterraUnit.banner300x250 => const Size(300, 250),
      AdsterraUnit.banner320x50 => const Size(320, 50),
      AdsterraUnit.nativeBanner => const Size(double.infinity, 280),
    };

    if (_collapsed) return const SizedBox.shrink();
    return Padding(
      padding: widget.margin,
      child: Center(
        child: SizedBox(
          width: size.width,
          height: size.height,
          child: HtmlElementView(viewType: _viewType),
        ),
      ),
    );
  }
}
