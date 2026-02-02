<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\Exports\UnregisteredVoterController;
use App\Http\Controllers\User\Exports\UserVoterController;
use App\Http\Controllers\User\Exports\SurveyController;
use App\Http\Controllers\Admin\Exports\ExportPartyController; 
use App\Http\Controllers\Admin\Exports\AdminUnregisteredVoterController;
use App\Http\Controllers\Admin\Exports\VoterController;
use App\Http\Controllers\Admin\Exports\ConstituencyController;
use App\Http\Controllers\Admin\Exports\UserController;
use App\Http\Controllers\Admin\Exports\LocationController;
use App\Http\Controllers\Admin\Exports\ManagerUserController;
use App\Http\Controllers\Admin\Exports\DuplicateVoterController;
use App\Http\Controllers\Admin\Exports\NewlyRegisteredVoterController;
use App\Http\Controllers\Admin\Exports\UpcomingBirthdaysController;
use App\Http\Controllers\Admin\Exports\DashboardStatsController;

// Arslan Exports
use App\Http\Controllers\Admin\Exports\QuestionController;
use App\Http\Controllers\Admin\Exports\AnswerController;
use App\Http\Controllers\Admin\Exports\VoterCardController;

 
use App\Http\Controllers\Manager\Exports\ManagerUnregisteredVoterController;
use App\Http\Controllers\Manager\Exports\ManagerVoterController;
use App\Http\Controllers\Manager\Exports\ManagerConstituencyController;
use App\Http\Controllers\Manager\Exports\ManagerUsersController;
use App\Http\Controllers\Manager\Exports\ManagerDuplicateVoterController;
use App\Http\Controllers\Manager\Exports\ManagerNewlyRegisteredVoterController;
use App\Http\Controllers\Manager\Exports\ManagerUpcomingBirthdaysController; 
use App\Http\Controllers\Manager\Exports\ManagerVoterCardController; 

    Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\Api\VoterImportController;
 
Route::get('/voters/import', [VoterImportController::class, 'insertData']);
Route::get('/voters/process', [VoterImportController::class, 'processFile']);


Route::middleware('jwt.auth')->group(function () {
    
    Route::middleware(['role:Admin'])->prefix('admin')->group(function () {


        Route::get('voter-cards-fnm/export', [VoterCardController::class, 'getVoterCard_FNM']);
        Route::get('voter-cards-plp/export', [VoterCardController::class, 'getVoterCard_PLP']);
        Route::get('voter-cards-dna/export', [VoterCardController::class, 'getVoterCard_DNA']);
        Route::get('voter-cards-unk/export', [VoterCardController::class, 'getVoterCard_UNK']); 
        Route::get('list-voter-card-result/export', [VoterCardController::class, 'listVoterCardResult']); 
        Route::get('election-day-report-one/export', [VoterCardController::class, 'electionDayReport_one']);
        // new file ni ban  rhi is liye question controller ma rhy ha function
        Route::get('get-constituencies/reports/export', [QuestionController::class, 'getConstituencyReports']);
        Route::get('getConstituencyReport1/export', [QuestionController::class, 'getConstituencyReport1']); 
        Route::get('getConstituencyReport2/export', [QuestionController::class, 'getConstituencyReport2']);
        Route::get('getConstituencyReport4/export', [QuestionController::class, 'getConstituencyReport4']);
        Route::get('voter-cards-report/export', [QuestionController::class, 'voterCardsReport']);
         
        Route::get('dashboard-stats/total/{type}', [DashboardStatsController::class, 'statsList']); 
        Route::get('user-activities/export', [DashboardStatsController::class, 'getUserActivities']);
        Route::get('parties/export', [ExportPartyController::class, 'export']); 
        Route::get('unregistered-voters/export', [AdminUnregisteredVoterController::class, 'export']);
        Route::get('unregistered-voters-diff-address/export', [AdminUnregisteredVoterController::class, 'getUnregisteredVotersDiffAddress']);
        Route::get('unregistered-voters/admin/get-contacted_voters/export', [AdminUnregisteredVoterController::class, 'get_contacted_votersExport']);
        Route::get('unregistered-voters/admin/get-uncontacted_voters/export', [AdminUnregisteredVoterController::class, 'get_uncontacted_votersExport']); 
        Route::get('voters-not-in-survey/export', [VoterController::class, 'getVotersNotInSurveyExport']); 
        Route::get('voters-in-survey/export', [VoterController::class, 'getVotersInSurvey']); 
        Route::get('died-voters-in-survey/export', [VoterController::class, 'getDiedVotersInSurvey']);
        Route::get('/voters-diff-address/export', [VoterController::class, 'getVotersDiffAddress']);
        // Route::get('users/voters-in-survey/export/{id}', [VoterController::class, 'getVotersInSurveyDetails']); 
        Route::get('users/voters-in-survey/export/{id}', [VoterController::class, 'getUserSurveys']); 
        Route::get('constituencies/export', [ConstituencyController::class, 'index']);
        Route::get('users/export', [UserController::class, 'export']); 
        Route::get('admin-users/export', [UserController::class, 'adminUsersExport']);
        Route::get('manager/export', [ManagerUserController::class, 'export']);
        Route::get('locations/export', [LocationController::class, 'export']);
        Route::get('/users/get-user-surveys-count/export/{id}', [UserController::class, 'getUserSurveyCount']);

        //Arslan Exports 
        Route::get('/duplicate-voters/export', [DuplicateVoterController::class, 'getDuplicateVoters']); 
        Route::get('/newly-registered-voters/export', [NewlyRegisteredVoterController::class, 'getNewlyRegisteredVoters']);
        Route::get('/upcoming-birthdays/export', [UpcomingBirthdaysController::class, 'getUpcomingBirthdays']);
       

        Route::get('/questions/export', [QuestionController::class, 'export']);
        Route::get('/answers/export', [AnswerController::class, 'export']);
    });  

    Route::middleware(['role:Manager'])->prefix('manager')->group(function () {


        Route::get('get-constituencies/reports/export', [ManagerUsersController::class, 'getConstituencyReports']); 
        Route::get('getConstituencyReport1/export', [ManagerUsersController::class, 'getConstituencyReport1']); 
        Route::get('getConstituencyReport2/export', [ManagerUsersController::class, 'getConstituencyReport2']);
        Route::get('getConstituencyReport4/export', [ManagerUsersController::class, 'getConstituencyReport4']);
       
         
        Route::get('stats-list', [ManagerUsersController::class, 'statsList']);
        Route::get('parties/export', [ExportPartyController::class, 'export']); 
        Route::get('unregistered-voters/export', [ManagerUnregisteredVoterController::class, 'export']);
        Route::get('unregistered-voters/manager/get-contacted_voters/export', [ManagerUnregisteredVoterController::class, 'get_contacted_votersExport']);
        Route::get('unregistered-voters/manager/get-uncontacted_voters/export', [ManagerUnregisteredVoterController::class, 'get_uncontacted_votersExport']); 
        Route::get('unregistered-voters-diff-address/export', [ManagerUnregisteredVoterController::class, 'getUnregisteredVotersDiffAddress']);
        Route::get('voters-not-in-survey/export', [ManagerVoterController::class, 'getVotersNotInSurveyExport']); 
        Route::get('voters-in-survey/export', [ManagerVoterController::class, 'getVotersInSurvey']); 
        Route::get('died-voters-in-survey/export', [ManagerVoterController::class, 'getDiedVotersInSurvey']);
        Route::get('users/voters-in-survey/export/{id}', [ManagerVoterController::class, 'getUserSurveys']); 
        Route::get('constituencies/export', [ManagerConstituencyController::class, 'index']);
        Route::get('users/export', [ManagerUsersController::class, 'export']); 
        Route::get('manager/export', [ManagerUserController::class, 'export']);
        Route::get('locations/export', [LocationController::class, 'export']);  
        Route::get('/get-user-surveys-count/export/{id}', [ManagerUsersController::class, 'getUserSurveyCount']); 
        Route::get('/voters-diff-address/export', [ManagerUsersController::class, 'getVotersDiffAddress']);
        //Arslan Exports 
        Route::get('/duplicate-voters/export', [ManagerDuplicateVoterController::class, 'getDuplicateVoters']);
        Route::get('/newly-registered-voters/export', [ManagerNewlyRegisteredVoterController::class, 'getNewlyRegisteredVoters']);
        Route::get('/upcoming-birthdays/export', [ManagerUpcomingBirthdaysController::class, 'getUpcomingBirthdays']);

        Route::get('voter-cards-fnm/export', [ManagerVoterCardController::class, 'getVoterCard_FNM']);
        Route::get('voter-cards-plp/export', [ManagerVoterCardController::class, 'getVoterCard_PLP']);
        Route::get('voter-cards-dna/export', [ManagerVoterCardController::class, 'getVoterCard_DNA']);
        Route::get('voter-cards-unk/export', [ManagerVoterCardController::class, 'getVoterCard_UNK']); 

    });  

    Route::middleware(['role:User'])->prefix('user')->group(function () {
        
        Route::get('unregistered-voters/export', [UnregisteredVoterController::class, 'export']);
        Route::get('unregistered-voters-diff-address',[UnregisteredVoterController::class, 'getUnregisteredVotersDiffAddress']); 
        Route::get('voters-not-in-survey/export', [UserVoterController::class, 'NotSurveyedExport']);    
        Route::get('voters-in-survey/export', [UserVoterController::class, 'SurveyedExport']);    
        Route::get('/unregistered-voters/get-uncontacted_voters/export', [UnregisteredVoterController::class, 'get_uncontacted_votersExport']);    
        Route::get('/unregistered-voters/get-contacted_voters/export', [UnregisteredVoterController::class, 'get_contacted_votersExport']);    
        Route::get('/surveys/list/export', [SurveyController::class, 'SurveyListExport']);  
          
    });
});