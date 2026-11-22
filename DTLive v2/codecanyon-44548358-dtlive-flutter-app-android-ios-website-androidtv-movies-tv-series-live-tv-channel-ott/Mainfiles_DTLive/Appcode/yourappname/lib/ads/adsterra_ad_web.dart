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

  @override
  void initState() {
    super.initState();
    _viewType = 'adsterra-${widget.unit.name}-${_nextViewId++}';
    ui_web.platformViewRegistry.registerViewFactory(_viewType, (viewId) {
      final element = html.DivElement()
        ..style.width = '100%'
        ..style.height = '100%'
        ..style.minHeight = '1px'
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
        root.append(options);
        root.append(script);
      case AdsterraUnit.nativeBanner:
        final script = html.ScriptElement()
          ..async = true
          ..setAttribute('data-cfasync', 'false')
          ..src =
              'https://bancadeltempoidea.org/21/f41c27f0f037b0f4fd483e03a969b04a';
        final container = html.DivElement()
          ..id = 'container-f41c27f0f037b0f4fd483e03a969b04a';
        root.append(script);
        root.append(container);
    }
  }

  @override
  Widget build(BuildContext context) {
    final size = switch (widget.unit) {
      AdsterraUnit.banner300x250 => const Size(300, 250),
      AdsterraUnit.banner320x50 => const Size(320, 50),
      AdsterraUnit.nativeBanner => const Size(double.infinity, 280),
    };

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
