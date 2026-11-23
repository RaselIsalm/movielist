class AdCreative {
  final int id;
  final String image;
  final String? clickUrl;
  final String altText;

  const AdCreative({
    required this.id,
    required this.image,
    this.clickUrl,
    required this.altText,
  });

  factory AdCreative.fromJson(Map<String, dynamic> json) {
    return AdCreative(
      id: int.tryParse(json['id'].toString()) ?? 0,
      image: (json['image'] ?? '').toString(),
      clickUrl: (json['click_url'] as String?)?.trim(),
      altText: (json['alt_text'] ?? 'Advertisement').toString(),
    );
  }
}
