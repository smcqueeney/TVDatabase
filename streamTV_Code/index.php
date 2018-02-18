<?php
/****************************************************************************
StreamTV PhP / streamTV project/ Web DataBase / by Sean McQueeney
This Program was developed to provide access to the streamTV service database
through a web application.

Files:  The application is made up of the following files

php: 	index.php - This file has all of the php code in one place.  It is found in 
		the public_html/streamTV/ directory of the code source.
		
		connect.php - This file contains the specific information for connecting to the
		database.  It is stored two levels above the index.php file to prevent the db 
		password from being viewable.
		
twig:	The twig files are used to set up templates for the html pages in the application.
		There are 13 twig files:
		- home.twig - home page for the web site
		- footer.twig - common footer for each of he html files
		- header.twig - common header for each of the html files
		- form.html.twig - template for forms html files (login and register)
		- register.html.twig - template for user to make an account
		- search.html.twig - template for search results
		- showinfo.html.twig - template for info about TV shows
		- Episode_Info.html.twig - template for information about TV episodes
		- actorinfo.html.twig - template for actors information
		- Show_Episode.html.twig - template for episodes for shows
		- queued_shows.html.twig - template for shows that are queued by the user (logged in)
		- watched.html.twig - template for TV watched by the user (logged in)
		- watching.html.twig - template for TV the user is watching (logged in)
		
		
		The twig files are found in the public_html/streamTV/views directory of the source code
		
Silex Files:  Composer was used to compose the needed Service Providers from the Silex 
		Framework.  The code created by composer is found in the vendor directory of the
		source code.  This folder should be stored in a directory called streamTV that is 
		at the root level of the application.  This code is used by this application and 
		has not been modified.


*****************************************************************************/
// Set time zone  
date_default_timezone_set('America/New_York');
/****************************************************************************   
Silex Setup:
The following code is necessary for one time setup for Silex 
It uses the appropriate services from Silex and Symfony and it
registers the services to the application.
*****************************************************************************/

// Objects we use directly
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Silex\Provider\FormServiceProvider;
// Pull in the Silex code stored in the vendor directory
require_once __DIR__.'/../../silex-files/vendor/autoload.php';
// Create the main application object
$app = new Silex\Application();
// For development, show exceptions in browser
$app['debug'] = true;
// For logging support
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
));
// Register validation handler for forms
$app->register(new Silex\Provider\ValidatorServiceProvider());
// Register form handler
$app->register(new FormServiceProvider());
// Register the session service provider for session handling
$app->register(new Silex\Provider\SessionServiceProvider());
// We don't have any translations for our forms, so avoid errors
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
        'translator.messages' => array(),
    ));
// Register the TwigServiceProvider to allow for templating HTML
$app->register(new Silex\Provider\TwigServiceProvider(), array(
        'twig.path' => __DIR__.'/views',
    ));
// Change the default layout 
// Requires including boostrap.css
$app['twig.form.templates'] = array('bootstrap_3_layout.html.twig');
/*************************************************************************
 Database Connection and Queries:
 The following code creates a function that is used throughout the program
 to query the MySQL database.  This section of code also includes the connection
 to the database.  This connection only has to be done once, and the $db object
 is used by the other code.
*****************************************************************************/
// Function for making queries.  The function requires the database connection
// object, the query string with parameters, and the array of parameters to bind
// in the query.  The function uses PDO prepared query statements.
function queryDB($db, $query, $params) {
    // Silex will catch the exception
    $stmt = $db->prepare($query);
    $results = $stmt->execute($params);
    $selectpos = stripos($query, "select");
    if (($selectpos !== false) && ($selectpos < 6)) {
        $results = $stmt->fetchAll();
    }
    return $results;
}
// Connect to the Database at startup, and let Silex catch errors
$app->before(function () use ($app) {
    include '../../connect.php';
    $app['db'] = $db;
});
/*************************************************************************
 Application Code:
 The following code implements the various functionalities of the application, usually
 through different pages.  Each section uses the Silex $app to set up the variables,
 database queries and forms.  Then it renders the pages using twig.
*****************************************************************************/
// Login Page
$app->match('/login', function (Request $request) use ($app) {
	// Use Silex app to create a form with the specified parameters - username and password
	// Form validation is automatically handled using the constraints specified for each
	// parameter
    $form = $app['form.factory']->createBuilder('form')
        ->add('uname', 'text', array(
            'label' => 'User Name',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('password', 'password', array(
            'label' => 'Password',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('login', 'submit', array('label'=>'Login'))
        ->getForm();
    $form->handleRequest($request);
    // Once the form is validated, get the data from the form and query the database to 
    // verify the username and password are correct
    $msg = '';
    if ($form->isValid()) {
        $db = $app['db'];
        $regform = $form->getData();
        $uname = $regform['uname'];
        $pword = $regform['password'];
        $query = "select password, custID 
        			from cust
        			where username = ?";
        $results = queryDB($db, $query, array($uname));
        # Ensure we only get one entry
        if (sizeof($results) == 1) {
            $retrievedPwd = $results[0][0];
            $custID = $results[0][1];
            // If the username and password are correct, create a login session for the user
            // The session variables are the username and the cust ID to be used in 
            // other queries for lookup.
            if (password_verify($pword, $retrievedPwd)) {
                $app['session']->set('is_user', true);
                $app['session']->set('user', $uname);
                $app['session']->set('custID', $custID);
                return $app->redirect('/streamTV/');
            }
        }
        else {
        	$msg = 'Invalid User Name or Password - Try again';
        }
        
    }
    // Use the twig form template to display the login page
    return $app['twig']->render('form.html.twig', array(
        'pageTitle' => 'Login',
        'form' => $form->createView(),
        'results' => $msg
    ));
});
// *************************************************************************
// Registration Page
$app->match('/register', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('uname', 'text', array(
            'label' => 'User Name',
            'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5)))
        ))
        ->add('password', 'repeated', array(
            'type' => 'password',
            'invalid_message' => 'Password and Verify Password must match',
            'first_options'  => array('label' => 'Password'),
            'second_options' => array('label' => 'Verify Password'),    
            'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5)))
        ))
        ->add('fname', 'text', array(
            'label' => 'First Name',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('lname', 'text', array(
            'label' => 'Last Name',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('email', 'text', array(
            'label' => 'Email',
            'constraints' => new Assert\Email()
        ))
        ->add('creditcard', 'text', array(
            'label' => 'Credit Card',
            'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 10)))
        ))
        ->add('submit', 'submit', array('label'=>'Register'))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $regform = $form->getData();
        $uname = $regform['uname'];
        $pword = $regform['password'];
        $fname = $regform['fname'];
        $lname = $regform['lname'];
        $email = $regform['email'];
        $creditcard = $regform['creditcard'];
        
        
        // Check to make sure the username is not already in use
        // If it is, display already in use message
        // If not, hash the password and insert the new cust into the database
        $db = $app['db'];
        $query = 'select * from cust where username = ?';
        $results = queryDB($db, $query, array($uname));
        if ($results) {
    		return $app['twig']->render('form.html.twig', array(
        		'pageTitle' => 'Register',
        		'form' => $form->createView(),
        		'results' => 'Username already exists - Try again'
        	));
        }
        else { 
        		$query = "select RIGHT(max(c.custID),3) from cust c"; // get the ### part of the most recently inserted custID
        		$custID = queryDB($db, $query, array());
        		$newID = $custID[0][0]; // take from array as real integer
        		$newID = (integer)$newID + 1;	//add one to it
        		$newID = 'cust0' . $newID; // concatenate cust0 back on to get new custID..doesnt work for past 100.
        		
        		$membersince = date("Y-m-d");	//current date
        		$renewaldate = date("Y-m-d"); // Can't figure out how to increase by one year  
        		
			$hashed_pword = password_hash($pword, PASSWORD_DEFAULT);
			$insertData = array($uname,$hashed_pword,$fname,$lname,$email,$creditcard,$newID,$membersince,$renewaldate);
       	 	$query = 'insert into cust 
        				(username, password, fname, lname, email, creditcard, custID, membersince, renewaldate)
        				values (?, ?, ?, ?, ?, ?,?,?,?)';
        	$results = queryDB($db, $query, $insertData);
	        // Maybe already log the user in, if not validating email
        	return $app->redirect('/streamTV/');
        }
    }
    return $app['twig']->render('form.html.twig', array(
        'pageTitle' => 'Register',
        'form' => $form->createView(),
        'results' => ''
    ));   
});
// *************************************************************************
 
// Actor Result Page
$app->get('/actor/{actID}', function (Silex\Application $app, $actID) {
    $db = $app['db'];
	//query for main cast information
    $query = "select distinct s.showid, s.title as title, m.role as role, a.fname as fname, a.lname as lname from shows s, main_cast m, actor a
    		where s.showid = m.showid and a.actID = m.actID and
    		a.actID = ?";
    $main_cast = queryDB($db, $query, array($actID));
    
    	//query for recurring cast information
    $query = "select distinct s.showid, s.title as title, r.role as role, a.fname as fname, a.lname as lname from shows s, recurring_cast r, actor a
    		where s.showid = r.showid and a.actID = r.actID and
    		a.actID = ?";
    $recurring_cast = queryDB($db, $query, array($actID));
    // Display results in item page
    return $app['twig']->render('actorinfo.html.twig', array(
        'pageTitle' =>'Actor Info',
        'main_cast' =>$main_cast,
        'recurring_cast' =>$recurring_cast
    ));
});
// *************************************************************************
// Show Information Page
$app ->get('/shows/{showid}', function (Silex\Application $app, $showid) {
	$db = $app['db'];
	
	//get custID and user information from sessions variables
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
		$custID = $app['session']->get('custID');
	}else{
		$user = '';
	}
	//query for general show information
	$query = "select * from shows s where s.showid = ?";
	$showinfo = queryDB($db, $query, array($showid));
	
	//query for main cast information
	$query = "select distinct m.role, a.fname, a.lname, a.actID from actor a, main_cast m, shows s 
	where s.showid = m.showid and a.actID = m.actID and s.showid = ?";
	$maininfo = queryDB($db, $query, array($showid));
	
	//query for recurring cast information
	$query = "select count(a.actID) as acount, a.fname, a.lname, a.actID, r.role from actor a, recurring_cast r, shows s, episode e 
	where e.episodeID = r.episodeID and r.showid = s.showid and e.showid = s.showid and a.actID = r.actID and s.showid = ? group by a.actID";
	$recurring_info = queryDB($db, $query, array($showid));
	
	//query to see if show is in queue
	$query = "select q.showid from cust c, cust_queue q where q.custID = c.custID and q.showid = ? and c.custID = ?";
	$inqueue = queryDB($db, $query, array($showid,$custID));
	if($inqueue != null){ //if it is in queue already
	return $app['twig']->render('showinfo.html.twig', array(
		'pageTitle' =>'Show Information',
		'showinfo' =>$showinfo,
		'maininfo' =>$maininfo,
		'recurring_info' =>$recurring_info,
		'inqueue' =>$inqueue,
		'user' =>$user
	));
	}else{ //if not, we need inqueue to be empty
		return $app['twig']->render('showinfo.html.twig', array(
		'pageTitle' =>'Show Information',
		'showinfo' =>$showinfo,
		'maininfo' =>$maininfo,
		'recurring_info' =>$recurring_info,
		'inqueue' =>'',
		'user' =>$user
	));
	}
});
// *************************************************************************
// Add to Queue
$app ->get('/addtoqueue/{showid}', function (Silex\Application $app, $showid) {
	$db = $app['db'];
	
	//get custID and user information from sessions variables
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
		$custID = $app['session']->get('custID');
	}else{
		$user = '';
	}
	
	$datequeued = date("Y-m-d"); //date queued is current date
	
	$insertData = array($custID, $showid, $datequeued);
	
	$query = 'insert into cust_queue 
        				(custID,showid,datequeued)
        				values (?, ?, ?)';
        	$results = queryDB($db, $query, $insertData);
	        // Return to home page
        	return $app->redirect('/streamTV/');
 
    return $app['twig']->render('form.html.twig', array(
        'pageTitle' => 'AddtoQueue',
        'form' => $form->createView(),
        'results' => ''
    ));   
});
	
// *************************************************************************
// Show Episodes Page
$app ->get('/show_episodes/{showid}', function (Silex\Application $app, $showid) {
	$db = $app['db'];
	
	$query = "select s.showid as shownum, s.title as stitle, e.title as etitle, e.airdate, e.episodeID, LEFT(e.episodeID,1) as season from episode e, shows s 
	where e.showid = s.showid and s.showid = ? order by e.airdate";
	$result = queryDB($db, $query, array($showid));
	
	return $app['twig']->render('Show_Episode.html.twig', array(
		'pageTitle' =>'Show Episodes',
		'result' =>$result
	));
});
// *************************************************************************
// Episode Info Page
$app ->get('/episodeinfo/{showid}&{episodeID}', function (Silex\Application $app, $showid, $episodeID) {
	$db = $app['db'];
	
	//get custID and user information from sessions variables
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
	}else{
		$user = '';
	}
	
	$query = "select s.showid, s.title as stitle, e.title as etitle, e.airdate as adate, e.episodeID from shows s, episode e 
	where s.showid = e.showid and s.showid = ? and e.episodeID = ?";
	$sresult = queryDB($db, $query, array($showid,$episodeID));
	
	$query = "select distinct m.role, a.fname, a.lname, a.actID AS actnum from actor a, main_cast m, shows s 
	where s.showid = m.showid and a.actID = m.actID and s.showid = ?";
	$mresult = queryDB($db, $query, array($showid));
	
	$query = "select distinct r.role, a.fname, a.lname, a.actID AS actnum from actor a, recurring_cast r, shows s, episode e 
	where s.showid = r.showid and a.actID = r.actID and r.episodeID = e.episodeID and s.showid = ? and e.episodeID = ?";
	$recresult = queryDB($db, $query, array($showid,$episodeID));
	
	return $app['twig']->render('Episode_Info.html.twig', array(
		'pageTitle' =>'Episode Info',
		'sresult' =>$sresult,
		'mresult' =>$mresult,
		'recresult' =>$recresult,
		'user' =>$user
	));
});
// *************************************************************************
// Search Result Page
$app->match('/search', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('search', 'text', array(
            'label' => 'Search',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $regform = $form->getData();
		$srch = $regform['search'];
		
		// Create prepared query for show titles
        $db = $app['db'];
		$query = "SELECT title, showid FROM shows where title like ?";
		$results = queryDB($db, $query, array('%'.$srch.'%'));
		
		// Create prepared query for actor names
		$query = "(SELECT fname, lname, actID FROM actor where lname like ?) UNION (SELECT fname, lname, actID FROM actor where fname like ?)" ;
		$results_2 = queryDB($db, $query, array('%'.$srch.'%', '%'.$srch.'%'));
		
        // Display results in search page
        return $app['twig']->render('search.html.twig', array(
            'pageTitle' => 'Search',
            'form' => $form->createView(),
            'results' => $results,
            'results_2' => $results_2
        ));
    }
    // If search box is empty, redisplay search page
    return $app['twig']->render('search.html.twig', array(
        'pageTitle' => 'Search',
        'form' => $form->createView(),
        'results' => '',
        'results_2' => ''
    ));
});
// *************************************************************************
// Queued Page
$app->match('/queue', function() use ($app) {
	// Get session variables
	$result='';
	//get custID and user information from sessions variables
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
		$custID = $app['session']->get('custID');
		
        $db = $app['db'];
        
        //find all queued shows for user with custID
		$query = "select s.title, s.showid, c.fname, c.lname, c.email, q.datequeued from shows s, cust c, cust_queue q 
		where s.showid = q.showid and c.custID = q.custID and c.custID = ?";
		$result= queryDB($db, $query, array($custID));
	}
	
	return $app['twig']->render('queued_shows.html.twig', array(
		'pageTitle' => 'Queue',
		'result' => $result
	));
});
// *************************************************************************
// Watched Page
$app ->get('/watched/{showid}', function (Silex\Application $app, $showid) {
	$db = $app['db'];
	
	$result = '';
	//get custID and user information from sessions variables
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
		$custID = $app['session']->get('custID');
		
	$query = "select s.showid, s.title as stitle, c.fname, c.lname, e.episodeID, e.title as etitle, max(w.datewatched) as datewatched
	 from shows s, cust c, episode e, watched w 
	where w.custID = c.custID and w.showid = s.showid and w.episodeID = e.episodeID and e.showid = s.showid and s.showid = ? and c.custID = ? 
	group by e.episodeID order by w.datewatched";
	$result = queryDB($db, $query, array($showid,$custID));
	
	}
	return $app['twig']->render('watched.html.twig', array(
		'pageTitle' =>'Watched Info',
		'result' =>$result
	));
});
// *************************************************************************
// Watching Page
$app ->get('/watch_episode/{showid}&{episodeID}', function (Silex\Application $app, $showid, $episodeID) {
	$db = $app['db'];
	$result = '';
	//get custID and user information from sessions variables
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
		$custID = $app['session']->get('custID');
	}
	
	//find last time episode was watched
	$query = "select max(w.datewatched) from watched w
	where w.showid = ? and w.episodeID = ? and w.custID = ?";
	
	$datewatched = queryDB($db,$query, array($showid, $episodeID, $custID));
	$datewatched = $datewatched[0][0]; // extract date value
	
	$currentdate = date("Y-m-d"); //current date
	
	if($currentdate != $datewatched){ //compare current date and query result
	
		$insertData = array($custID, $currentdate, $episodeID, $showid);
       		$query = 'insert into watched 
        		(custID, datewatched, episodeID, showid)
        			values (?, ?, ?, ?)';
        	$result = queryDB($db, $query, $insertData);
  
	}
	
	$query = "select s.title as stitle, e.title as etitle from shows s, episode e 
	where e.showid = s.showid and s.showid = ? and e.episodeID = ?";
	$info = queryDB($db,$query, array($showid, $episodeID));
	      
        return $app['twig']->render('watching.html.twig', array(
		'pageTitle' =>'Watching',
		'result' =>$result,
		'info' =>$info
		
	));
});
	
// *************************************************************************
// Logout
$app->get('/logout', function () use ($app) {
	$app['session']->clear();
	return $app->redirect('/streamTV/');
});
	
// *************************************************************************
// Home Page
$app->get('/', function () use ($app) {
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
	}
	else {
		$user = '';
	}
	return $app['twig']->render('home.twig', array(
        'user' => $user,
        'pageTitle' => 'Home'));
});
// *************************************************************************
// Run the Application
$app->run();