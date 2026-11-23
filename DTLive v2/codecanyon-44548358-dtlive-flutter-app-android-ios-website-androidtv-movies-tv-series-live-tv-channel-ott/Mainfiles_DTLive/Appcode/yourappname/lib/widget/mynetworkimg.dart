import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../widget/myimage.dart';

// ignore: must_be_immutable
class MyNetworkImage extends StatefulWidget {
  String imageUrl;
  double? height, width;
  dynamic fit;

  MyNetworkImage({
    super.key,
    required this.imageUrl,
    required this.fit,
    this.height,
    this.width,
  });

  @override
  State<MyNetworkImage> createState() => _MyNetworkImageState();
}

class _MyNetworkImageState extends State<MyNetworkImage> {

  Widget _skeleton() {
    return Container(
      width: widget.width,
      height: widget.height,
      color: const Color(0xFF20242A),
      child: const DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment(-1.0, -0.2),
            end: Alignment(1.0, 0.2),
            colors: [Color(0xFF20242A), Color(0xFF323842), Color(0xFF20242A)],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (widget.imageUrl.contains('no_img')) {
      return MyImage(
        width: widget.width,
        height: widget.height,
        imagePath: ((widget.height ?? 0) > (widget.width ?? 0))
            ? "no_image_port.png"
            : "no_image_land.png",
        fit: ((widget.height ?? 0) > (widget.width ?? 0))
            ? BoxFit.fitWidth
            : BoxFit.cover,
      );
    }
    if (kIsWeb) {
      return SizedBox(
        height: widget.height,
        width: widget.width,
        child: Image.network(
          widget.imageUrl,
          fit: widget.fit,
          loadingBuilder: (context, child, loadingProgress) {
            if (loadingProgress == null) {
              return child;
            }
            return _skeleton();
          },
          errorBuilder: (context, error, stackTrace) {
            return MyImage(
              width: widget.width,
              height: widget.height,
              imagePath: ((widget.height ?? 0) > (widget.width ?? 0))
                  ? "no_image_port.png"
                  : "no_image_land.png",
              fit: ((widget.height ?? 0) > (widget.width ?? 0))
                  ? BoxFit.fitWidth
                  : BoxFit.cover,
            );
          },
        ),
      );
    }
    return SizedBox(
      height: widget.height,
      width: widget.width,
      child: CachedNetworkImage(
        imageUrl: widget.imageUrl,
        fit: widget.fit,
        imageBuilder: (context, imageProvider) => Container(
          decoration: BoxDecoration(
            image: DecorationImage(image: imageProvider, fit: widget.fit),
          ),
        ),
        placeholder: (context, url) {
          return _skeleton();
        },
        errorWidget: (context, url, error) {
          return MyImage(
            width: widget.width,
            height: widget.height,
            imagePath: ((widget.width ?? 0) > (widget.height ?? 0))
                ? "no_image_land.png"
                : "no_image_port.png",
            fit: BoxFit.cover,
          );
        },
      ),
    );
  }
}
