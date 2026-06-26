<?php

use App\Http\Controllers\Api\AccountingController;
use App\Http\Controllers\Api\AlumniProfileController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DuesController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ExcoController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Middleware\ApiCors;
use App\Http\Middleware\AuthorizeApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware([ApiCors::class])->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware([AuthorizeApiToken::class])->group(function () {
        // Auth
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        // Alumni Profiles
        Route::get('profile', [AlumniProfileController::class, 'index']);
        Route::get('profile/me', [AlumniProfileController::class, 'me']);
        Route::get('profile/{id}', [AlumniProfileController::class, 'show']);
        Route::put('profile/{id}', [AlumniProfileController::class, 'update']);

        // Meetings
        Route::apiResource('meetings', MeetingController::class);
        Route::post('meetings/{meeting}/attendance', [MeetingController::class, 'markAttendance']);
        Route::post('meetings/{meeting}/minutes', [MeetingController::class, 'saveMinutes']);

        // Events
        Route::apiResource('events', EventController::class);
        Route::post('events/{event}/register', [EventController::class, 'register']);
        Route::delete('events/{event}/register', [EventController::class, 'cancelRegistration']);

        // Donations
        Route::get('campaigns', [DonationController::class, 'campaigns']);
        Route::post('campaigns', [DonationController::class, 'createCampaign']);
        Route::get('campaigns/{id}', [DonationController::class, 'show']);
        Route::put('campaigns/{id}', [DonationController::class, 'update']);
        Route::delete('campaigns/{id}', [DonationController::class, 'destroy']);
        Route::get('donations', [DonationController::class, 'index']);
        Route::post('donations', [DonationController::class, 'store']);

        // EXCO & Elections
        Route::apiResource('excos', ExcoController::class);
        Route::get('elections', [ExcoController::class, 'elections']);
        Route::post('elections', [ExcoController::class, 'createElection']);
        Route::post('elections/{election}/vote', [ExcoController::class, 'vote']);

        // Chat Groups & Messages
        Route::get('chats', [ChatController::class, 'index']);
        Route::post('chats', [ChatController::class, 'store']);
        Route::post('chats/{group}/join', [ChatController::class, 'joinGroup']);
        Route::get('chats/{group}/messages', [ChatController::class, 'messages']);
        Route::post('chats/{group}/messages', [ChatController::class, 'sendMessage']);

        // Accounting
        Route::get('accounts', [AccountingController::class, 'index']);
        Route::post('accounts', [AccountingController::class, 'store']);
        Route::get('account-categories', [AccountingController::class, 'categories']);
        Route::post('account-categories', [AccountingController::class, 'storeCategory']);

        // Reports
        Route::get('reports', [ReportController::class, 'index']);

        // Monthly Dues & Payments
        Route::get('dues', [DuesController::class, 'index']);
        Route::post('dues/initialize', [DuesController::class, 'initialize']);
        Route::post('dues/verify', [DuesController::class, 'verify']);
        Route::get('dues/transactions', [DuesController::class, 'transactions']);

        // Access Control — Super Admin only
        Route::middleware(['super_admin'])->group(function () {
            Route::get('roles', [RoleController::class, 'index']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::put('roles/{id}', [RoleController::class, 'update']);
            Route::delete('roles/{id}', [RoleController::class, 'destroy']);
            Route::post('roles/{id}/permissions', [RoleController::class, 'syncPermissions']);
            Route::get('permissions', [RoleController::class, 'permissions']);
            Route::get('users/{id}/roles', [RoleController::class, 'userRoles']);
            Route::post('users/{id}/roles', [RoleController::class, 'assignRoles']);
        });

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    });
});
