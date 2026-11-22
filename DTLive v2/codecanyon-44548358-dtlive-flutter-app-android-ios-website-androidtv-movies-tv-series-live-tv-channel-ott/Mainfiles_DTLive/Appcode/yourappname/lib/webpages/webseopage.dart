import 'package:flutter/material.dart';
import 'package:flutter_html/flutter_html.dart';

import '../utils/color.dart';
import '../utils/constant.dart';
import '../webservice/dioclient.dart';

class WebSeoPage extends StatefulWidget {
  final String path;

  const WebSeoPage({super.key, required this.path});

  @override
  State<WebSeoPage> createState() => _WebSeoPageState();
}

class _WebSeoPageState extends State<WebSeoPage> {
  late Future<Map<String, dynamic>> _metadata;

  @override
  void initState() {
    super.initState();
    _metadata = _loadMetadata();
  }

  Future<Map<String, dynamic>> _loadMetadata() async {
    final response = await DioClient.instance.dio.get(
      'seo',
      queryParameters: {'path': widget.path},
    );
    final data = response.data;
    if (data is! Map) {
      throw StateError('The public page metadata was not returned.');
    }
    return Map<String, dynamic>.from(data);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: appBgColor,
      body: FutureBuilder<Map<String, dynamic>>(
        future: _metadata,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return Center(
              child: Text(
                'Page not found',
                style: TextStyle(color: white, fontSize: 18),
              ),
            );
          }

          final data = snapshot.data!;
          final title = (data['title'] ?? 'Link Viral')
              .toString()
              .replaceFirst(' | Link Viral', '');
          final subtitle = (data['subtitle'] ?? '').toString();
          final content = (data['content'] ?? data['description'] ?? '')
              .toString();

          return SafeArea(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(24, 32, 24, 48),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 960),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: TextStyle(
                          color: white,
                          fontSize: 32,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (subtitle.trim().isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(
                          subtitle,
                          style: TextStyle(
                            color: white.withValues(alpha: 0.65),
                            fontSize: 16,
                          ),
                        ),
                      ],
                      const SizedBox(height: 24),
                      Html(
                        data: content,
                        style: {
                          'body': Style(
                            color: white,
                            fontSize: FontSize(16),
                            lineHeight: const LineHeight(1.6),
                          ),
                          'a': Style(color: colorPrimary),
                        },
                      ),
                      const SizedBox(height: 40),
                      Text(
                        Constant.appName,
                        style: TextStyle(
                          color: white.withValues(alpha: 0.45),
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
