import 'sectionlistmodel.dart';

class FeedItemsPage {
  final int? status;
  final String? message;
  final List<Datum> result;
  final String? nextCursor;
  final bool hasMore;

  const FeedItemsPage({
    this.status,
    this.message,
    this.result = const <Datum>[],
    this.nextCursor,
    this.hasMore = false,
  });

  factory FeedItemsPage.fromJson(Map<String, dynamic> json) {
    final raw = json['result'];
    return FeedItemsPage(
      status: json['status'] as int?,
      message: json['message'] as String?,
      result: raw is List
          ? raw
              .whereType<Map<String, dynamic>>()
              .map(Datum.fromJson)
              .toList(growable: false)
          : const <Datum>[],
      nextCursor: json['next_cursor'] as String?,
      hasMore: json['has_more'] == true,
    );
  }
}
