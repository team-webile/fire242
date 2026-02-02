<?php
 
use App\Http\Controllers\Api\VoterImportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;    
use App\Http\Controllers\Api\Auth\LoginController;  
use App\Http\Controllers\Api\Auth\RegisterController;  
use App\Http\Controllers\Api\Admin\VoterController;
use App\Http\Controllers\Api\Admin\QuestionController;
use App\Http\Controllers\Api\Admin\AnswerController;
use App\Http\Controllers\Api\Admin\VoterBirthdayController;
use App\Http\Controllers\Api\User\SurveyController;
use App\Http\Controllers\Api\Admin\PartyController;
use App\Http\Controllers\Api\Admin\CountryController;
use App\Http\Controllers\Api\Admin\LocationController;
use App\Http\Controllers\Api\Admin\ConstituencyController;
use App\Http\Controllers\Api\Admin\MapController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\ManagerController;
use App\Http\Controllers\Api\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\UnregisteredVoterController as AdminUnregisteredVoterController;
use App\Http\Controllers\Api\Admin\Admin_SurveyController;
use App\Http\Controllers\Api\User\DashboardController;
use App\Http\Controllers\Api\User\UserVoterController;
use App\Http\Controllers\Api\User\UnregisteredVoterController;
use App\Http\Controllers\Api\Admin\DashboardStatsController;
use App\Http\Controllers\Api\User\VoterNoteController;
use App\Http\Controllers\Api\User\UserVoterBirthdayController;
use App\Http\Controllers\Api\Admin\AdminVoterNoteController;    
use App\Http\Controllers\Api\Admin\SystemSettingsController;    
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Manager\ManagerUserController;
use App\Http\Controllers\Api\Manager\ManagerSystemSettingsController;
use App\Http\Controllers\Api\Manager\ManagerVoterCardController;

use App\Http\Controllers\Api\Admin\PageController;
use App\Http\Controllers\Api\Admin\ManagerPageController; 
use App\Http\Controllers\Api\Admin\VoterCardController;
use App\Http\Controllers\Api\User\UserVoterCardController;
use App\Http\Controllers\Api\Admin\RolePermissionController; 

use App\Http\Controllers\OCRController;




Route::post('/upload-ocr-image', [OCRController::class, 'processOCRImage']);



Route::prefix('auth')->group(function () {
    // Registration routes
    Route::post('register', [RegisterController::class, 'register']);

   
    // Login routes
    Route::post('login', [LoginController::class, 'login']);
    Route::post('logout', [LoginController::class, 'logout']); 
 
  
});



 
Route::middleware([ 'jwt.auth'])->group(function () {
    Route::get('/get-all-parties', [PartyController::class, 'getAllParties']); 
    Route::get('/livesearch-get-all-parties', [PartyController::class, 'livesearchGetAllParties']); 
    Route::get('/get-all-constituencies', [ConstituencyController::class, 'getAllConstituencies']); 
     
    Route::middleware(['role:Admin'])->prefix('admin')->group(function () {



        Route::get('voter', [Admin_SurveyController::class, 'getVoter']);
        Route::get('questions-answers', [Admin_SurveyController::class, 'getQuestionsAnswers']); 

        Route::get('get-constituencies/reports', [ConstituencyController::class, 'getConstituencyReports']); 
        Route::get('getConstituencyReport1', [ConstituencyController::class, 'getConstituencyReport1']);
        Route::get('getConstituencyReport2', [ConstituencyController::class, 'getConstituencyReport2']);
        Route::get('getConstituencyReport4', [ConstituencyController::class, 'getConstituencyReport4']);
        Route::get('voter-cards-report', [ConstituencyController::class, 'voterCardsReport']);


        Route::apiResource('pages', PageController::class); 
        Route::post('pages/update-status', [PageController::class, 'update_status']);

        Route::apiResource('manager-pages', ManagerPageController::class); 
        Route::post('manager-pages/update-status', [ManagerPageController::class, 'update_status']);



        Route::post('users-in-bounds', [MapController::class, 'getUsersInBounds']);
        Route::get('question-stats', [DashboardStatsController::class, 'getQuestionStats']);
        Route::get('user-activities', [DashboardStatsController::class, 'getUserActivities']);
        Route::get('dashboard/stats', [DashboardStatsController::class, 'index']);
        Route::get('dashboard-stats/total/{type}', [DashboardStatsController::class, 'statsList']);

        Route::post('voters_list/import', [VoterImportController::class, 'import']);
       
        Route::get('voter-cards-fnm', [VoterCardController::class, 'getVoterCard_FNM']);
        Route::get('voter-cards', [VoterCardController::class, 'getVoterCard']);
        Route::get('voter-cards-stats', [VoterCardController::class, 'voter_cards_stats']);
        Route::get('voter-cards-plp', [VoterCardController::class, 'getVoterCard_PLP']);
        Route::get('voter-cards-dna', [VoterCardController::class, 'getVoterCard_DNA']);
        Route::get('voter-cards-unk', [VoterCardController::class, 'getVoterCard_UNK']); 
        Route::post('add-voter-card-result', [VoterCardController::class, 'addVoterCardResult']); 
        Route::get('get-voter-card-result/{id}', [VoterCardController::class, 'getVoterCardResult']); 
        Route::post('update-voter-card-result/{id}', [VoterCardController::class, 'updateVoterCardResult']); 
        Route::delete('delete-voter-card-result/{id}', [VoterCardController::class, 'deleteVoterCardResult']); 
        Route::get('list-voter-card-result', [VoterCardController::class, 'listVoterCardResult']);  
        Route::get('get-voter-with-id/{id}', [VoterCardController::class, 'getVoterWithId']);  

        Route::get('print_voters', [VoterController::class, 'print_voters']); 
        Route::get('voters', [VoterController::class, 'index']);
        Route::get('national-registery-list', [VoterController::class, 'nationalRegisteryList']);

        Route::get('election-day-report-one', [VoterController::class, 'electionDayReport_one']); 
        Route::get('election-day-graph', [VoterController::class, 'electionDayGraph']);
        Route::get('polling-election-dayGraph', [VoterController::class, 'PollingelectionDayGraph']);
        Route::get('voters/address', [VoterController::class, 'addressSearch']);
        Route::get('voters/unregister/address', [VoterController::class, 'unregisterAddressSearch']);
        Route::get('voters/newly-registered', [VoterController::class, 'newlyRegistered']);
        Route::get('voters/import/status/{jobId}', [VoterImportController::class, 'status']); 
      
        Route::get('voters-in-survey', [VoterController::class, 'getVotersInSurvey']);
        Route::get('died-voters-in-survey', [VoterController::class, 'getDiedVotersInSurvey']);
        Route::get('voters-diff-address', [VoterController::class, 'getVotersDiffAddress']); 
        Route::get('voters-not-in-survey', [VoterController::class, 'getVotersNotInSurvey']);
        Route::get('voters-history/{id}', [VoterController::class, 'getVotersHistory']);
        Route::get('duplicate-voters', [VoterController::class, 'duplicateVoters']);
        Route::get('birthday-voters', [VoterBirthdayController::class, 'birthdayVoters']);
        Route::get('birthday-voters-contacted/{id}', [VoterBirthdayController::class, 'birthdayVotersContacted']);

        




        Route::get('system-settings', [SystemSettingsController::class, 'show']);
        Route::put('system-settings', [SystemSettingsController::class, 'update']);


        // Route::get('unregistered-voters', [VoterController::class, 'getUnregisteredVoters']); 

        Route::get('surveys/list', [Admin_SurveyController::class, 'index']);
        Route::get('/surveys/challenge/{id}', [Admin_SurveyController::class, 'make_challange']);

        Route::get('surveys/{id}', [Admin_SurveyController::class, 'show']);
        Route::post('surveys/{id}', [Admin_SurveyController::class, 'update']);
        Route::get('surveyer-search', [Admin_SurveyController::class, 'surveyer_search']);
 

        Route::get('unregistered-voters-diff-address', [AdminUnregisteredVoterController::class, 'getUnregisteredVotersDiffAddress']); 
        Route::get('unregistered-voters', [AdminUnregisteredVoterController::class, 'index']); 
        Route::get('unregistered-voters/{id}', [AdminUnregisteredVoterController::class, 'show']);
        Route::put('unregistered-voters/{id}', [AdminUnregisteredVoterController::class, 'update']);
        Route::delete('unregistered-voters/{id}', [AdminUnregisteredVoterController::class, 'destroy']);
        // Route::post('unregistered-voters/{id}/contacted', [AdminUnregisteredVoterController::class, 'updateContacted']);
        Route::get('unregistered-voters/admin/get-contacted_voters', [AdminUnregisteredVoterController::class, 'get_contacted_voters']);
        Route::get('unregistered-voters/admin/get-uncontacted_voters', [AdminUnregisteredVoterController::class, 'get_uncontacted_voters']); 

        Route::get('unregistered-voters/notes/list/{unregistered_voter_id}', [AdminVoterNoteController::class, 'index']);
        Route::post('unregistered-voters/{unregistered_voter_id}/contacted', [AdminVoterNoteController::class, 'store']);
        Route::get('unregistered-voters/notes/{id}', [AdminVoterNoteController::class, 'show']);
        Route::put('unregistered-voters/notes/{id}', [AdminVoterNoteController::class, 'update']);
        Route::delete('unregistered-voters/notes/{id}', [AdminVoterNoteController::class, 'destroy']);


        Route::get('get-profile', [ProfileController::class, 'getProfile']);
        Route::post('update-profile', [ProfileController::class, 'updateProfile']);
        // Countries Routes
        Route::get('countries', [CountryController::class, 'index']);
        Route::post('countries', [CountryController::class, 'store']);
        Route::get('countries/{country}', [CountryController::class, 'show']);
        Route::put('countries/{country}', [CountryController::class, 'update']);
        Route::delete('countries/{country}', [CountryController::class, 'destroy']); 

        // Locations Routes
        Route::get('locations', [LocationController::class, 'index']);
        Route::post('locations', [LocationController::class, 'store']);
        Route::get('locations/{location}', [LocationController::class, 'show']);
        Route::put('locations/{location}', [LocationController::class, 'update']);
        Route::delete('locations/{location}', [LocationController::class, 'destroy']); 
        Route::post('locations/update-positions', [LocationController::class, 'updatePositions']);



        Route::get('search-countries', [LocationController::class, 'searchCountries']);
      
        // Constituencies Routes
        Route::get('get_constituencies', [ConstituencyController::class, 'getConstituencies']);
        Route::get('constituencies', [ConstituencyController::class, 'index']);
        Route::post('constituencies', [ConstituencyController::class, 'store']);
        Route::get('constituencies/{constituency}', [ConstituencyController::class, 'show']);
        Route::put('constituencies/{constituency}', [ConstituencyController::class, 'update']);
        Route::delete('constituencies/{constituency}', [ConstituencyController::class, 'destroy']); 
        Route::get('constituencies/island/get-islands', [ConstituencyController::class, 'getIslands']); 
        Route::post('constituencies/update-positions', [ConstituencyController::class, 'updatePositions']);



       


        // Parties Routes
        Route::get('party/search', [PartyController::class, 'livesearch']);  
        Route::get('get-all-parties', [PartyController::class, 'getAllParties']); 
        Route::get('parties', [PartyController::class, 'index']); 
        Route::post('parties', [PartyController::class, 'store']);
        Route::get('parties/{party}', [PartyController::class, 'show']);
        Route::put('parties/{party}', [PartyController::class, 'update']);
        Route::get('parties/delete/{party}', [PartyController::class, 'destroy']);  
        Route::post('parties/update-positions', [PartyController::class, 'updatePositions']);

        // Questions Routes
        Route::get('questions', [QuestionController::class, 'index']);
        Route::post('questions', [QuestionController::class, 'store']);
        Route::get('questions/{question}', [QuestionController::class, 'show']);
        Route::put('questions/{question}', [QuestionController::class, 'update']);
        Route::delete('questions/{question}', [QuestionController::class, 'destroy']);
        Route::post('questions/update/positions', [QuestionController::class, 'updatePositions']);

        // Answers Routes
        Route::get('answers/create', [AnswerController::class, 'create']);
        Route::get('answers', [AnswerController::class, 'index']);
        Route::post('answers', [AnswerController::class, 'store']);
        Route::get('answers/{answer}', [AnswerController::class, 'show']);
        Route::put('answers/{answer}', [AnswerController::class, 'update']);
        Route::delete('answers/{answer}', [AnswerController::class, 'destroy']);
        Route::post('answers/update/positions', [AnswerController::class, 'updatePositions']);
        
        
        // Settings Routes
        Route::get('settings', [SettingsController::class, 'getSettings']);
        Route::post('settings', [SettingsController::class, 'store']);
        Route::put('settings/{setting}', [SettingsController::class, 'update']);
        Route::get('settings/delete/{id}', [SettingsController::class, 'destroy']);
        Route::get('settings/{type}', [SettingsController::class, 'getByType']);
        Route::get('settings/single/{id}', [SettingsController::class, 'show']);
        Route::post('settings/update-positions', [SettingsController::class, 'updatePositions']);

        // Users Routes
        Route::get('users', [UserController::class, 'index']); 
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
        Route::get('users/surveys/{id}', [UserController::class, 'getUserSurveys']);
        Route::get('search-constituencies', [UserController::class, 'getConstituencies']);
        Route::get('users/get-user-surveys-count/{id}', [UserController::class, 'getUserSurveyCount']);  



        Route::get('admin-users', [AdminUserController::class, 'index']);
        Route::post('admin-users', [AdminUserController::class, 'store']);
        Route::get('admin-users/{user}', [AdminUserController::class, 'show']);
        Route::put('admin-users/{user}', [AdminUserController::class, 'update']);
        Route::delete('admin-users/{user}', [AdminUserController::class, 'destroy']);




        Route::get('managers', [ManagerController::class, 'index']);
        Route::post('managers', [ManagerController::class, 'store']);
        Route::get('managers/{manager}', [ManagerController::class, 'show']);
        Route::put('managers/{manager}', [ManagerController::class, 'update']);
        Route::delete('managers/{manager}', [ManagerController::class, 'destroy']);
        Route::get('managers/get-settings/{id}', [ManagerController::class, 'getSettings']);

    });
    


  


    Route::middleware(['role:User'])->prefix('user')->group(function () {
         
        // Route::middleware(['check.page.permission'])->group(function () {

        //     Route::get('voters-list', [UserVoterController::class, 'getVotersList']);
        //     Route::get('voters-list/newly-registered', [UserVoterController::class, 'newlyRegistered']);
     
        // });
        Route::post('add-voter-card-result', [UserVoterCardController::class, 'addVoterCardResult']); 
        Route::get('get-voter-card-result/{id}', [UserVoterCardController::class, 'getVoterCardResult']); 
        Route::post('update-voter-card-result/{id}', [UserVoterCardController::class, 'updateVoterCardResult']); 
        Route::delete('delete-voter-card-result/{id}', [UserVoterCardController::class, 'deleteVoterCardResult']); 
        Route::get('list-voter-card-result', [UserVoterCardController::class, 'listVoterCardResult']); 
        Route::get('get-voter-with-id/{id}', [UserVoterCardController::class, 'getVoterWithId']);  

        Route::post('upload-voter-card', [DashboardController::class, 'upload_voter_card']); 
        Route::get('get-voter-card-images', [DashboardController::class, 'get_voter_card_images']);  
        Route::get('question-stats', [DashboardController::class, 'getQuestionStats']);
        Route::get('fatch-all-user-permissions', [DashboardController::class, 'fatch_all_user_permissions']);

        Route::get('party/search', [DashboardController::class, 'livesearch']);  
        Route::get('stats-list', [DashboardController::class, 'statsList']);
        Route::post('unregistered-voters', [UnregisteredVoterController::class, 'unregister_voter_store']);
        Route::get('unregistered-voters-diff-address', [UnregisteredVoterController::class, 'getUnregisteredVotersDiffAddress']); 
        Route::get('questions-answers', [SurveyController::class, 'getQuestionsAnswers']); 
        Route::get('constituencies', [SurveyController::class, 'getUserConstituency']);
        Route::get('voters/suggestions', [SurveyController::class, 'getSuggestions']);
        Route::get('all-constituency', [SurveyController::class, 'allConstituency']);
        Route::get('surveys/list', [SurveyController::class, 'index']);
        Route::post('surveys', [SurveyController::class, 'store']);
        Route::get('surveys/{id}', [SurveyController::class, 'show']);
        Route::put('surveys/{id}', [SurveyController::class, 'update']);
        Route::delete('surveys/{id}', [SurveyController::class, 'destroy']);
        Route::post('update-diff-address', [SurveyController::class, 'updateDiffAddress']);
        Route::get('voter', [SurveyController::class, 'getVoter']);
        Route::get('voter/{voterId}', [SurveyController::class, 'getVoter']);
        Route::get('surveyer-search', [SurveyController::class, 'surveyer_search']);
 
        Route::get('system-settings', [DashboardController::class, 'getSystemSettings']);
        Route::get('get-profile', [DashboardController::class, 'getProfile']);
        Route::post('update-profile', [DashboardController::class, 'updateProfile']);
        Route::get('stats', [DashboardController::class, 'stats']);
        Route::get('app-stats', [DashboardController::class, 'appStats']);
         Route::get('voters-list', [UserVoterController::class, 'getVotersList']);
        Route::get('voters-history/{id}', [UserVoterController::class, 'getVotersHistory']);
        Route::get('voters-list/newly-registered', [UserVoterController::class, 'newlyRegistered']);
        Route::get('duplicate-voters', [UserVoterController::class, 'duplicateVoters']);
        Route::get('birthday-voters', [UserVoterBirthdayController::class, 'userBirthdayVoters']);
        Route::get('birthday-voters-contacted/{id}', [UserVoterBirthdayController::class, 'userBirthdayVotersContacted']);
        Route::get('voters-in-survey', [UserVoterController::class, 'getVotersInSurvey']);
        Route::get('died-voters-in-survey', [UserVoterController::class, 'getDiedVotersInSurvey']); 
        Route::get('voters-diff-address', [UserVoterController::class, 'getVotersDiffAddress']);
        Route::get('voters-not-in-survey', [UserVoterController::class, 'getVotersNotInSurvey']); 
        Route::get('get-user-surveys-count', [UserVoterController::class, 'getUserSurveyCount']);  
        Route::get('voters/address', [UserVoterController::class, 'addressSearch']);
        Route::get('voters/unregister/address', [UserVoterController::class, 'unregisterAddressSearch']);
        Route::get('national-registery-list', [UserVoterController::class, 'nationalRegisteryList']);
        Route::get('unregistered-voters', [UnregisteredVoterController::class, 'index']); 
        Route::get('unregistered-voters-diff-address', [UnregisteredVoterController::class, 'getUnregisteredVotersDiffAddress']); 
        Route::get('unregistered-voters/{id}', [UnregisteredVoterController::class, 'show']);
        Route::put('unregistered-voters/{id}', [UnregisteredVoterController::class, 'update']);
        Route::delete('unregistered-voters/{id}', [UnregisteredVoterController::class, 'destroy']);
        Route::post('update-diff-address-unregister-voter', [UnregisteredVoterController::class, 'updateDiffAddress']);  
        // Route::post('unregistered-voters/{id}/contacted', [UnregisteredVoterController::class, 'updateContacted']);
        // Change from using resource route parameters to explicit routes
        Route::get('unregistered-voters/{id}/get-contacted_voters', [UnregisteredVoterController::class, 'get_contacted_voters']);
        Route::get('unregistered-voters/{id}/get-uncontacted_voters', [UnregisteredVoterController::class, 'get_uncontacted_voters']); 

 
        // Voter Notes Routes
        Route::get('unregistered-voters/notes/list/{unregistered_voter_id}', [VoterNoteController::class, 'index']);
        Route::post('unregistered-voters/{unregistered_voter_id}/contacted', [VoterNoteController::class, 'store']);
        Route::get('unregistered-voters/notes/{id}', [VoterNoteController::class, 'show']);
        Route::put('unregistered-voters/notes/{id}', [VoterNoteController::class, 'update']);
        Route::delete('unregistered-voters/notes/{id}', [VoterNoteController::class, 'destroy']);  
         
   
        Route::get('parties', [VoterNoteController::class, 'parties']);

    }); 

    Route::middleware(['role:Manager'])->prefix('manager')->group(function () {

        

        
        Route::get('get-constituencies/reports', [ManagerVoterCardController::class, 'getConstituencyReports']); 
        Route::get('getConstituencyReport1', [ManagerVoterCardController::class, 'getConstituencyReport1']);
        Route::get('getConstituencyReport2', [ManagerVoterCardController::class, 'getConstituencyReport2']);


        Route::get('voter-cards-fnm', [ManagerVoterCardController::class, 'getVoterCard_FNM']);
        Route::get('voter-cards-plp', [ManagerVoterCardController::class, 'getVoterCard_PLP']);
        Route::get('voter-cards-dna', [ManagerVoterCardController::class, 'getVoterCard_DNA']);
        Route::get('voter-cards-unk', [ManagerVoterCardController::class, 'getVoterCard_UNK']); 

        
        Route::get('system-settings', [ManagerSystemSettingsController::class, 'show']);
        Route::put('system-settings', [ManagerSystemSettingsController::class, 'update']);

        Route::resource('system-settings', ManagerSystemSettingsController::class);

        Route::get('fatch-all-manager-permissions', [ManagerUserController::class, 'fatch_all_manager_permissions']); 
        Route::get('question-stats', [ManagerUserController::class, 'getQuestionStats']);
        Route::get('stats-list', [ManagerUserController::class, 'statsList']);
        Route::get('party/search', [ManagerUserController::class, 'livesearch']);  
        Route::get('get_constituencies', [ManagerUserController::class, 'get_all_constituencies']);
        Route::get('users', [ManagerUserController::class, 'index']);
        Route::post('users', [ManagerUserController::class, 'store']);
        Route::get('users/{user}', [ManagerUserController::class, 'show']);
        Route::put('users/{user}', [ManagerUserController::class, 'update']);
        Route::delete('users/{user}', [ManagerUserController::class, 'destroy']);
        Route::get('users/surveys/{id}', [ManagerUserController::class, 'getUserSurveys']);
        Route::get('search-constituencies', [ManagerUserController::class, 'getConstituencies']);   
        Route::get('get-user-surveys-count/{id}', [ManagerUserController::class, 'getUserSurveyCount']);  
        Route::get('constituencies', [App\Http\Controllers\Api\Manager\SurveyController::class, 'getUserConstituency']);
        Route::get('constituencies_get', [App\Http\Controllers\Api\Manager\SurveyController::class, 'getUserConstituency_get']);
        Route::get('voters/suggestions', [App\Http\Controllers\Api\Manager\SurveyController::class, 'getSuggestions']);
        Route::get('surveys/list', [App\Http\Controllers\Api\Manager\SurveyController::class, 'index']);
        Route::get('surveys/{id}', [App\Http\Controllers\Api\Manager\SurveyController::class, 'show']);
        Route::post('surveys/{id}', [App\Http\Controllers\Api\Manager\SurveyController::class, 'update']);
        Route::post('surveys', [App\Http\Controllers\Api\Manager\SurveyController::class, 'store']);
        Route::get('voter', [App\Http\Controllers\Api\Manager\SurveyController::class, 'getVoter']);
        Route::get('questions-answers', [App\Http\Controllers\Api\Manager\SurveyController::class, 'getQuestionsAnswers']); 

        Route::get('voter/{voterId}', [App\Http\Controllers\Api\Manager\SurveyController::class, 'getVoter']);
        Route::get('surveyer-search', [App\Http\Controllers\Api\Manager\SurveyController::class, 'surveyer_search']);
 
        Route::get('get-profile', [App\Http\Controllers\Api\Manager\DashboardController::class, 'getProfile']);
        Route::post('update-profile', [App\Http\Controllers\Api\Manager\DashboardController::class, 'updateProfile']);
        Route::get('stats', [App\Http\Controllers\Api\Manager\DashboardController::class, 'stats']);
        Route::get('print-voters-list', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'print_voters']);
        Route::get('voters-list', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'getVotersList']);
        Route::get('voter-cards-report',[App\Http\Controllers\Api\Manager\UserVoterController::class, 'voterCardsReport']); 
        Route::get('election-day-report-one',[App\Http\Controllers\Api\Manager\UserVoterController::class, 'electionDayReport_one']);
        Route::get('voters-diff-address', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'getVotersDiffAddress']);
        Route::get('voters-history/{id}', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'getVotersHistory']);
        Route::get('voters-list/newly-registered', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'newlyRegistered']);
        Route::get('voters/newly-registered', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'newlyRegistered']);
        
        Route::get('duplicate-voters', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'duplicateVoters']);
        Route::get('birthday-voters', [App\Http\Controllers\Api\Manager\UserVoterBirthdayController::class, 'userBirthdayVoters']);
        Route::get('birthday-voters-contacted/{id}', [App\Http\Controllers\Api\Manager\UserVoterBirthdayController::class, 'userBirthdayVotersContacted']);
        Route::get('national-registery-list', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'nationalRegisteryList']);
        Route::get('voters-in-survey', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'getVotersInSurvey']); 
        Route::get('died-voters-in-survey', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'getDiedVotersInSurvey']);
        Route::get('voters-not-in-survey', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'getVotersNotInSurvey']);
        Route::get('voters/address', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'addressSearch']);
        Route::get('voters/unregister/address', [App\Http\Controllers\Api\Manager\UserVoterController::class, 'unregisterAddressSearch']);
        Route::get('unregistered-voters', [App\Http\Controllers\Api\Manager\UnregisteredVoterController::class, 'index']);
        Route::get('unregistered-voters/{id}', [App\Http\Controllers\Api\Manager\UnregisteredVoterController::class, 'show']);
        Route::get('unregistered-voters/{id}/get-contacted_voters', [App\Http\Controllers\Api\Manager\UnregisteredVoterController::class, 'get_contacted_voters']);
        Route::get('unregistered-voters/{id}/get-uncontacted_voters', [App\Http\Controllers\Api\Manager\UnregisteredVoterController::class, 'get_uncontacted_voters']);

        Route::get('unregistered-voters/notes/list/{unregistered_voter_id}', [App\Http\Controllers\Api\Manager\VoterNoteController::class, 'index']);
        Route::get('unregistered-voters/notes/{id}', [App\Http\Controllers\Api\Manager\VoterNoteController::class, 'show']);
        Route::get('parties', [App\Http\Controllers\Api\Manager\VoterNoteController::class, 'parties']);

        Route::delete('surveys/{id}', [App\Http\Controllers\Api\Manager\SurveyController::class, 'destroy']);
        Route::delete('unregistered-voters/{id}', [App\Http\Controllers\Api\Manager\UnregisteredVoterController::class, 'destroy']);
        Route::delete('unregistered-voters/notes/{id}', [App\Http\Controllers\Api\Manager\VoterNoteController::class, 'destroy']);

        Route::get('unregistered-voters-diff-address', [App\Http\Controllers\Api\Manager\UnregisteredVoterController::class, 'getUnregisteredVotersDiffAddress']);  

        Route::post('add-voter-card-result', [ManagerVoterCardController::class, 'addVoterCardResult']); 
        Route::get('get-voter-card-result/{id}', [ManagerVoterCardController::class, 'getVoterCardResult']); 
        Route::post('update-voter-card-result/{id}', [ManagerVoterCardController::class, 'updateVoterCardResult']); 
        Route::delete('delete-voter-card-result/{id}', [ManagerVoterCardController::class, 'deleteVoterCardResult']); 
        Route::get('list-voter-card-result', [ManagerVoterCardController::class, 'listVoterCardResult']); 
        Route::get('get-voter-with-id/{id}', [ManagerVoterCardController::class, 'getVoterWithId']);  

    }); 
         

});  

