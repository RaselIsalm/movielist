import 'dart:io';
import 'package:dio/dio.dart';
import 'package:dio/io.dart'; // for IOHttpClientAdapter (Dio v5)
import 'package:flutter/foundation.dart';

import '../utils/constant.dart';

class DioClient {
  // Singleton
  static final DioClient instance = DioClient._internal();
  late final Dio dio;

  factory DioClient() => instance;

  DioClient._internal() {
    dio = Dio(
      BaseOptions(
        baseUrl: Constant.baseUrl,
        // The home feed can fan out to R2-backed media URLs on a cold edge.
        // Keep transient latency from being interpreted as an empty catalog;
        // the providers retain their last successful data while retrying.
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 30),
        headers: {
          HttpHeaders.contentTypeHeader: 'application/json',
        },
      ),
    );

    dio.interceptors.add(
      InterceptorsWrapper(
        onError: (error, handler) async {
          final request = error.requestOptions;
          final retryCount = (request.extra['dtlive_retry_count'] as int?) ?? 0;
          final path = request.path.toLowerCase();
          final retryablePath = _retryableReadPaths.any(path.contains);
          final transientStatus = <int>{408, 425, 429, 500, 502, 503, 504}
              .contains(error.response?.statusCode);
          final transientError = error.type == DioExceptionType.connectionTimeout ||
              error.type == DioExceptionType.connectionError ||
              error.type == DioExceptionType.receiveTimeout ||
              transientStatus;

          // Only retry idempotent catalog/read requests. Mutations such as
          // payments, likes and watch progress must never be duplicated.
          if (!retryablePath || !transientError || retryCount >= 2) {
            handler.next(error);
            return;
          }

          request.extra['dtlive_retry_count'] = retryCount + 1;
          await Future<void>.delayed(
            Duration(milliseconds: 250 * (1 << retryCount)),
          );
          try {
            final response = await dio.fetch<dynamic>(request);
            handler.resolve(response);
          } on DioException catch (retryError) {
            handler.next(retryError);
          }
        },
      ),
    );

    // Keep-alive, connection pool (mobile/desktop). Browser handles this on web.
    if (!kIsWeb) {
      final adapter = dio.httpClientAdapter as IOHttpClientAdapter;
      // ignore: deprecated_member_use
      adapter.onHttpClientCreate = (HttpClient client) {
        client.idleTimeout = const Duration(seconds: 30);
        client.connectionTimeout = const Duration(seconds: 15);
        client.maxConnectionsPerHost = 8;
        return client;
      };
    }
  }

  static const List<String> _retryableReadPaths = <String>[
    'general_setting',
    'get_pages',
    'get_social_link',
    'get_type',
    'get_category',
    'get_language',
    'get_channel',
    'get_banner',
    'section_list',
    'feed_sections',
    'feed_section_items',
    'section_detail',
    'content_detail',
    'get_releted_content',
    'content_by_category',
    'content_by_language',
    'content_by_cast',
    'content_by_channel',
    'search_content',
    'get_shorts_list',
    'get_shorts_episode',
  ];

  /// Call this when your token changes
  void setAuthToken(String? token) {
    if (token == null || token.isEmpty) {
      dio.options.headers.remove(HttpHeaders.authorizationHeader);
    } else {
      dio.options.headers[HttpHeaders.authorizationHeader] = 'Bearer $token';
    }
  }

  /// If you need to set/override any header at runtime
  void setHeader(String key, String? value) {
    if (value == null) {
      dio.options.headers.remove(key);
    } else {
      dio.options.headers[key] = value;
    }
  }
}
