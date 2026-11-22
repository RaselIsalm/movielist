import 'dart:io';

import 'package:flutter_locales/flutter_locales.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:provider/provider.dart';

import '../provider/bottombarprovider.dart';
import '../provider/generalprovider.dart';
import '../provider/homeprovider.dart';
import '../provider/sectiondataprovider.dart';
import '../utils/color.dart';
import '../utils/constant.dart';
import '../utils/loadingoverlay.dart';
import '../utils/sharedpre.dart';
import '../widget/myimage.dart';
import '../widget/mytext.dart';
import '../utils/utils.dart';

class LoginSocial extends StatefulWidget {
  const LoginSocial({super.key});

  @override
  State<LoginSocial> createState() => LoginSocialState();
}

class LoginSocialState extends State<LoginSocial> {
  late GeneralProvider generalProvider;
  SharedPre sharedPre = SharedPre();

  String? email,
      userName,
      strType,
      strDeviceType,
      strDeviceToken,
      strPrivacyAndTNC;
  File? mProfileImg;
  final FirebaseAuth _auth = FirebaseAuth.instance;
  final GoogleSignIn googleSignIn = GoogleSignIn.instance;
  bool initialized = false;
  String? webServerClientId;

  @override
  void initState() {
    super.initState();
    generalProvider = Provider.of<GeneralProvider>(context, listen: false);
    _getDeviceToken();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _getData();
    });
  }

  Future<void> _getDeviceToken() async {
    try {
      webServerClientId = await sharedPre.read(Constant.googleClientIdKey);
      printLog("_getDeviceToken webServerClientId ===> $webServerClientId");
      if (Platform.isAndroid) {
        strDeviceType = "1";
      } else {
        strDeviceType = "2";
      }
      strDeviceToken = await FirebaseMessaging.instance.getToken();
    } catch (e) {
      printLog("_getDeviceToken Exception ===> $e");
    }
    printLog("_getDeviceToken strDeviceToken ===> $strDeviceToken");
    printLog("_getDeviceToken strDeviceType ====> $strDeviceType");
  }

  Future<void> _getData() async {
    String? privacyUrl, termsConditionUrl;
    await generalProvider.getPages();
    if (!generalProvider.loading) {
      if (generalProvider.pagesModel.status == 200 &&
          generalProvider.pagesModel.result != null) {
        if ((generalProvider.pagesModel.result?.length ?? 0) > 0) {
          for (
            var i = 0;
            i < (generalProvider.pagesModel.result?.length ?? 0);
            i++
          ) {
            if ((generalProvider.pagesModel.result?[i].pageName ?? "")
                .toLowerCase()
                .contains("privacy")) {
              privacyUrl = generalProvider.pagesModel.result?[i].url;
            }
            if ((generalProvider.pagesModel.result?[i].pageName ?? "")
                .toLowerCase()
                .contains("terms")) {
              termsConditionUrl = generalProvider.pagesModel.result?[i].url;
            }
          }
        }
      }
    }
    printLog('privacyUrl ==> $privacyUrl');
    printLog('termsConditionUrl ==> $termsConditionUrl');

    strPrivacyAndTNC = await Utils.getPrivacyTandCText(
      privacyUrl ?? "",
      termsConditionUrl ?? "",
    );
    Future.delayed(Duration.zero).then((value) {
      if (!mounted) return;
      setState(() {});
    });
  }

  @override
  void dispose() {
    LoadingOverlay().hide();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: appBgColor,
      body: SafeArea(
        child: Stack(
          children: [
            SingleChildScrollView(
              scrollDirection: Axis.vertical,
              child: Container(
                width: MediaQuery.of(context).size.width,
                height: MediaQuery.of(context).size.height,
                margin: const EdgeInsets.fromLTRB(25, 0, 25, 0),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 170,
                      height: 60,
                      alignment: Alignment.centerLeft,
                      child: MyImage(
                        fit: BoxFit.fill,
                        imagePath: "appicon.png",
                      ),
                    ),
                    const SizedBox(height: 25),
                    MyText(
                      color: titleTextColor,
                      text: "welcomeback",
                      fontsizeNormal: 20,
                      fontsizeWeb: 25,
                      multilanguage: true,
                      fontweight: FontWeight.bold,
                      maxline: 1,
                      overflow: TextOverflow.ellipsis,
                      textalign: TextAlign.center,
                      fontstyle: FontStyle.normal,
                    ),
                    const SizedBox(height: 7),
                    MyText(
                      color: descTextColor,
                      text: "sign_in_subtitle",
                      fontsizeNormal: 14,
                      fontsizeWeb: 15,
                      multilanguage: true,
                      fontweight: FontWeight.w500,
                      maxline: 2,
                      overflow: TextOverflow.ellipsis,
                      textalign: TextAlign.center,
                      fontstyle: FontStyle.normal,
                    ),
                    const SizedBox(height: 30),

                    /* Google Login Button */
                    Container(
                      width: MediaQuery.of(context).size.width,
                      height: 52,
                      padding: const EdgeInsets.fromLTRB(25, 0, 25, 0),
                      margin: const EdgeInsets.only(bottom: 15),
                      decoration: BoxDecoration(
                        color: white,
                        borderRadius: BorderRadius.circular(26),
                      ),
                      alignment: Alignment.center,
                      child: InkWell(
                        onTap: () {
                          printLog("Clicked on : ====> loginWith Google");
                          _gmailLogin();
                        },
                        borderRadius: BorderRadius.circular(26),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            MyImage(
                              width: 30,
                              height: 30,
                              imagePath: "ic_google.png",
                              fit: BoxFit.contain,
                            ),
                            const SizedBox(width: 30),
                            MyText(
                              color: black,
                              text: "loginwithgoogle",
                              fontsizeNormal: 14,
                              fontsizeWeb: 16,
                              multilanguage: true,
                              fontweight: FontWeight.w600,
                              maxline: 1,
                              overflow: TextOverflow.ellipsis,
                              textalign: TextAlign.center,
                              fontstyle: FontStyle.normal,
                            ),
                          ],
                        ),
                      ),
                    ),

                    /* Privacy & TermsCondition link */
                    if (strPrivacyAndTNC != null)
                      Utils.htmlTexts(strPrivacyAndTNC),

                    /* Facebook Login Button */
                    // Container(
                    //   width: MediaQuery.of(context).size.width,
                    //   height: 52,
                    //   padding: const EdgeInsets.fromLTRB(25, 0, 25, 0),
                    //   decoration: BoxDecoration(
                    //     color: white,
                    //     borderRadius: BorderRadius.circular(26),
                    //   ),
                    //   alignment: Alignment.center,
                    //   child: InkWell(
                    //     onTap: () {
                    //       printLog("Clicked on : ====> loginWith Facebook");
                    //       facebookLogin();
                    //     },
                    //     borderRadius: BorderRadius.circular(26),
                    //     child: Row(
                    //       mainAxisAlignment: MainAxisAlignment.center,
                    //       children: [
                    //         MyImage(
                    //           width: 30,
                    //           height: 30,
                    //           imagePath: "ic_facebook.png",
                    //           fit: BoxFit.contain,
                    //         ),
                    //         const SizedBox(width: 30),
                    //         MyText(
                    //           color: black,
                    //           text: "loginwithfacebook",
                    //           fontsizeNormal: 14,
                    //           fontsizeWeb: 16,
                    //           multilanguage: true,
                    //           fontweight: FontWeight.w600,
                    //           maxline: 1,
                    //           overflow: TextOverflow.ellipsis,
                    //           textalign: TextAlign.center,
                    //           fontstyle: FontStyle.normal,
                    //         ),
                    //       ],
                    //     ),
                    //   ),
                    // ),
                  ],
                ),
              ),
            ),
            Positioned(
              top: 20,
              right: 20,
              child: InkWell(
                borderRadius: BorderRadius.circular(25),
                onTap: () {
                  Utils.exitPage(context);
                },
                child: Container(
                  width: 40,
                  height: 40,
                  alignment: Alignment.center,
                  padding: const EdgeInsets.all(10),
                  child: MyImage(
                    fit: BoxFit.contain,
                    imagePath: "ic_close.png",
                    color: defaultIconColor,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  /* Google Login */
  Future<void> _initGoogleSignIn() async {
    try {
      await googleSignIn.initialize(
        clientId: Platform.isIOS ? null : webServerClientId,
        serverClientId: Platform.isIOS ? null : webServerClientId,
      );
      initialized = true;
    } catch (e) {
      printLog("_initGoogleSignIn GoogleSignIn Error ===> $e");
      initialized = false;
      return;
    }
  }

  Future<void> _gmailLogin() async {
    try {
      if (!initialized) {
        await _initGoogleSignIn();
      }
      GoogleSignInAccount? googleUser = await googleSignIn.authenticate(
        scopeHint: ['email', 'profile'],
      );
      if (googleUser.authentication.idToken == null) return;

      GoogleSignInAccount user = googleUser;

      printLog('_gmailLogin ===> id : ${user.id}');
      printLog('_gmailLogin ===> email : ${user.email}');
      printLog('_gmailLogin ===> displayName : ${user.displayName}');
      printLog('_gmailLogin ===> photoUrl : ${user.photoUrl}');

      if (!mounted) return;
      LoadingOverlay().show(context);

      UserCredential userCredential;
      final GoogleSignInAuthentication googleAuth = user.authentication;
      final AuthCredential credential = GoogleAuthProvider.credential(
        idToken: googleAuth.idToken,
      );

      userCredential = await _auth.signInWithCredential(credential);
      assert(await userCredential.user?.getIdToken() != null);
      printLog("_gmailLogin UserName ======> ${user.displayName}");
      printLog("_gmailLogin UserEmail =====> ${user.email}");
      printLog("_gmailLogin UserPhotoUrl ==> ${user.photoUrl}");
      String firebasedid = userCredential.user?.uid ?? "";
      printLog('_gmailLogin firebasedid ===> $firebasedid');

      /* Save PhotoUrl in File */
      mProfileImg = await Utils.saveImageInStorage(user.photoUrl ?? "");
      printLog('_gmailLogin mProfileImg ===> $mProfileImg');

      /* Hide overlay before showing referral dialog */
      LoadingOverlay().hide();
      if (!mounted) return;
      final referralCode = await Utils.showReferralDialog(context);
      if (!mounted) return;
      LoadingOverlay().show(context);
      checkAndNavigate(
        user.email,
        user.displayName ?? "",
        "2",
        referenceCode: referralCode,
      );
    } on FirebaseAuthException catch (e) {
      printLog('_gmailLogin Firebase Error Code ==> ${e.code.toString()}');
      printLog('_gmailLogin Firebase Error =======> ${e.message.toString()}');
      if (!mounted) return;
      LoadingOverlay().hide();
    } on Exception catch (e) {
      printLog('_gmailLogin Exception ==> $e');
      if (!mounted) return;
      LoadingOverlay().hide();
    }
  }

  Future<void> checkAndNavigate(
    String mail,
    String displayName,
    String type, {
    String? referenceCode,
  }) async {
    email = mail;
    userName = displayName;
    strType = type;
    printLog('checkAndNavigate email ========>> $email');
    printLog('checkAndNavigate userName =====>> $userName');
    printLog('checkAndNavigate strType ======>> $strType');
    printLog('checkAndNavigate mProfileImg ==>> $mProfileImg');
    printLog('checkAndNavigate referenceCode => $referenceCode');

    final homeProvider = Provider.of<HomeProvider>(context, listen: false);
    final sectionDataProvider = Provider.of<SectionDataProvider>(
      context,
      listen: false,
    );
    final bottombarProvider = Provider.of<BottombarProvider>(
      context,
      listen: false,
    );
    await generalProvider.loginWithSocial(
      email,
      userName,
      strType,
      Constant.deviceName,
      strDeviceType,
      strDeviceToken,
      mProfileImg,
      referenceCode: referenceCode,
    );
    printLog('checkAndNavigate loading ==>> ${generalProvider.loading}');

    if (!generalProvider.loading) {
      if (generalProvider.loginSocialModel.status == 200) {
        printLog('Login Successfull!');
        Utils.saveUserCreds(
          userID: generalProvider.loginSocialModel.result?[0].id.toString(),
          fullName: generalProvider.loginSocialModel.result?[0].fullName
              .toString(),
          userName: generalProvider.loginSocialModel.result?[0].userName
              .toString(),
          userEmail: generalProvider.loginSocialModel.result?[0].email
              .toString(),
          userMobile: generalProvider.loginSocialModel.result?[0].mobileNumber
              .toString(),
          userImage: generalProvider.loginSocialModel.result?[0].image
              .toString(),
          userPremium: generalProvider.loginSocialModel.result?[0].isBuy
              .toString(),
          userType: generalProvider.loginSocialModel.result?[0].type.toString(),
          deviceType: generalProvider.loginSocialModel.result?[0].deviceType
              .toString(),
          deviceToken: generalProvider.loginSocialModel.result?[0].deviceToken
              .toString(),
        );

        // Set UserID for Next
        Constant.userID = generalProvider.loginSocialModel.result?[0].id
            .toString();
        printLog('Constant userID ==>> ${Constant.userID}');
        await Future.wait([
          bottombarProvider.setBottomNavIndex(0),
          bottombarProvider.toggleVisibility(true),
          homeProvider.setLoading(true),
          sectionDataProvider.getSectionBanner("0", "1"),
          sectionDataProvider.getSectionList("0", "1", 1),
          /* Initialize Hive */
          Utils.initializeHiveBoxes(),
        ]);

        if (!mounted) return;
        LoadingOverlay().hide();
        if (!mounted) return;
        Utils.redirectToMainPage(context: context);
      } else {
        // Hide Progress Dialog
        if (!mounted) return;
        LoadingOverlay().hide();
        Utils.showSnackbar(
          context,
          "fail",
          "${generalProvider.loginSocialModel.message}",
          false,
        );
      }
    }
  }
}
