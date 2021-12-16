<?php

use App\Http\Controllers\AnnouncementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\CountryCodeController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserLikeCommentController;
use App\Http\Controllers\PassportAuthController;
use App\Http\Controllers\SocialController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AttractionController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\TrailController;
use App\Http\Controllers\TrailHeadController;
use App\Http\Controllers\TrailInfoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::get('incorrectToken', function () {
    return response(['Status' => 'incorrect token!'], 401);
})->name('incorrectToken');

Route::post('register', [PassportAuthController::class, 'register']);
Route::middleware('auth:api')->post('profile', [PassportAuthController::class, 'createProfile']);

Route::middleware('cors')->group(function () {
    Route::post('login', [PassportAuthController::class, 'login']);

    Route::post('auth/social/callback', [SocialController::class, 'handleSocialCallback']);

    Route::post('/password/forget', [PassportAuthController::class, 'forgetPassword']);
    Route::post('/password/confirm', [PassportAuthController::class, 'confirmVerificationCodes']);
});

Route::middleware('auth:api')->post('/password/change', [PassportAuthController::class, 'changePassword']);

Route::middleware('auth:api')->get('index', function () {
    return ['Status' => 'Logged!'];
});

Route::middleware('cors')->group(function () {
    Route::get('/country', [CountryController::class, 'index']);

    Route::get('/countrycode', [CountryCodeController::class,'index']);

    Route::resource('/collection', CollectionController::class);

    Route::resource('/trail', TrailController::class);

    Route::resource('/user', UserController::class);

    Route::resource('/home', HomeController::class);

    Route::resource('/article', ArticleController::class);

    Route::resource('/classification', ClassificationController::class);

    Route::resource('/favorite', FavoritesController::class);
    Route::get('/favorites', [FavoritesController::class, 'Inquire']);
    Route::post('/favorite/delete', [FavoriteController::class, 'delete']);

    Route::resource('/comment',CommentController::class);
    Route::resource('/likeComment',UserLikeCommentController::class);

    Route::resource('/trailinfo',TrailInfoController::class);
    Route::resource('/trailHead',TrailHeadController::class);
    Route::resource('/announcement',AnnouncementController::class);
    Route::resource('/attraction',AttractionController::class);
});
