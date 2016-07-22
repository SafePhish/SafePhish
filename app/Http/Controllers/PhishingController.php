<?php namespace App\Http\Controllers;

use App\DBManager;
use App\Email as Email;
use app\EmailConfiguration;
use app\Exceptions\ConfigurationException;
use app\Exceptions\EmailException;
use App\Exceptions\QueryException;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\PDOIterator;
use app\TemplateConfiguration;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Illuminate\Http\Request;
use App\User;
use PhpSpec\Exception\Example\FailureException;
use Symfony\Component\Validator\Exception\OutOfBoundsException;

class PhishingController extends Controller {

	public function index()	{
		return view('displays.displayHome');
	}

	/**
	 * webbugRedirect
	 * Handles when webbugs get called. If request URI contains the word 'email', executes email webbug otherwise executes website webbug
	 *
	 * @param 	string		$id		Contains UniqueURLId that references specific user and specific project ID
	 */
	public function webbugRedirect($id) {
		$urlId = substr($id,0,15);
		$projectId = substr($id,15,16);
		try {
			$db = new DBManager();
			$sql = "SELECT USR_Username FROM gaig_users.users WHERE USR_UniqueURLId=?;";
			$bindings = array($urlId);
			$result = $db->query($sql,$bindings);
			$result = $result->fetch(\PDO::FETCH_ASSOC);
			$username = $result[0]['USR_Username'];
			if(strpos($_SERVER['REQUEST_URI'],'email') !== false) {
				$this->webbugExecutionEmail($username,$projectId);
			} else {
				$this->webbugExecutionWebsite($username,$projectId);
			}
		} catch(Exception $e) {
			//caught exception in webbug already logged
            //retry? otherwise do nothing
		}

	}

	/**
	 * webbugExecutionEmail
	 * Email specific execution of the webbug tracker.
	 *
	 * @param 	string		$username			Username of user passed from webbugRedirect
	 * @param 	string		$projectId			Project ID to create a filter choice in the results
	 */
	private function webbugExecutionEmail($username,$projectId) {
		$sql = "INSERT INTO gaig_users.email_tracking (EML_Id,EML_Ip,EML_Host,EML_Username,EML_ProjectName,
					EML_AccessTimestamp) VALUES (null,?,?,?,?,?);";
		$this->webbugRootExecution($projectId,$sql,$username);
	}

	/**
	 * webbugExecutionWebsite
	 * Website specific execution of the webbug tracker.
	 *
	 * @param 	string		$username			Username of user passed from webbugRedirect
	 * @param 	string		$projectId			Project ID to create a filter choice in the results
	 */
	private function webbugExecutionWebsite($username,$projectId) {
		$sql = "INSERT INTO gaig_users.website_tracking (WBS_Id,WBS_Ip,WBS_Host,
					WBS_BrowserAgent,WBS_ReqPath,WBS_Username,WBS_ProjectName,WBS_AccessTimestamp) 
					VALUES (null,?,?,?,?,?,?,?);";
		$this->webbugRootExecution($projectId,$sql,$username);
	}

	/**
	 * webbugRootExecution
	 * Common values for webbug execution. Returns array of values to calling method.
	 *
	 * @param	string		$parentSql			SQL to be executed based on whether the parent is Email or Website
	 * @param	string		$username			Username of the user to be used in binding of statement
	 * @return 	array|null						Returns null if IP is hidden or not given, otherwise gives needed input
	 */
	private function webbugRootExecution($projectId,$parentSql,$username) {
		if(!empty($_SERVER['REMOTE_ADDR'])) {
			try {
				$db = new DBManager();
				$ip = $_SERVER['REMOTE_ADDR'];
				$host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
				$sql = "SELECT PRJ_ProjectName FROM gaig_users.projects WHERE PRJ_ProjectId=?;";
				$bindings = array($projectId);
				$result = $db->query($sql,$bindings);
				$result = $result->fetch(\PDO::FETCH_ASSOC);
				$projectName = $result[0]['PRJ_ProjectName'];
				$timestamp = date("Y-m-d H:i:s");
				$parentBindings = array($ip,$host,$username,$projectName,$timestamp);
				$db->query($parentSql,$parentBindings);
			} catch(Exception $e) {
                //caught exception in webbug already logged
                //retry? otherwise do nothing
            }
		}
	}

	public function create() {
		return redirect()->to('/breachReset');
	}

	public function breachReset() {
		return view("passwordReset.resetPage1");
	}

	public function breachVerify() {
		return view("passwordReset.resetPage2");
	}

	public function store()
	{
		return redirect()->to('/breachReset/verifyUser');
	}

	public function edit($id)
	{
		//
	}

	public function update($id)
	{
		//
	}

	public function destroy($id)
	{
		//
	}

	/*private function random_str($length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
	{
		$str = '';
		$max = mb_strlen($keyspace, '8bit') - 1;
		for ($i = 0; $i < $length; ++$i) {
			$str .= $keyspace[random_int(0, $max)];
		}
		return $str;
	}

	public function sendEmail(Request $request) {
		$fromEmail = $request['fromEmail'];
		$fromPass = $request['fromPass'];
		$host = $request['hostName'];
		$port = $request['port'];
		putenv("MAIL_HOST=$host");
		putenv("MAIL_PORT=$port");
		putenv("MAIL_USERNAME=$fromEmail");
		putenv("MAIL_PASSWORD=$fromPass");

		$subject = $request['subject'];
		$projectName = $request['projectName'];
		$projectId = substr($projectName,strpos($projectName,'_'));
		$projectName = substr($projectName,0,strpos($projectName,'_')-1);
		$companyName = $request['companyName'];
		$emailTemplate = 'emails.' . $request['emailTemplate'];
		$emailTemplateType = substr($request['emailTemplate'],0,3);
		$emailTemplateTarget = substr($request['emailTemplate'],3,1);

		$db = $this->openDatabaseDefault();
		$sql = "SELECT * FROM gaig_users.users;";
		if(!$result = $db->query($sql)) {
			$this->databaseErrorLogging($sql,$db);
			exit;
		}
		if($result->num_rows === 0) {
			echo "Sorry. There are no users in this database.";
			exit;
		}
		while($user = $result->fetch_assoc()) {
			if($emailTemplateType != substr($user['USR_ProjectMostRecent'],-5,3) || $emailTemplateTarget != substr($user['USR_ProjectMostRecent'],-2,1)) {
				$urlID = null;
				if(!is_null($user['USR_UniqueURLId'])) {
					$urlID = $user['USR_UniqueURLId'];
				}
				while(is_null($urlID)) {
					$urlID = $this->random_str(15);
					$sql = "SELECT * FROM gaig_users.users WHERE USR_UniqueURLId=$urlID;";
					$tempResult = $db->query($sql);
					//if($tempResult->num_rows === 0) {
					//	break;
					//}
					//$urlID = null;
				}
				$username = $user['USR_Username'];
				$toEmail = $user['USR_Email'];
				$lastName = $user['USR_LastName'];
				$firstName = $user['USR_FirstName'];
				//$projectName = 'bscG6_9_16';
				/*
                 * NAMING FORMAT:
                 * 1. bsc/adv : First three letters defines whether its basic or advanced scam
                 * 2. G/T : This letter defines whether it's a generic scam or a targeted scam
                 * 3. Project Start Date
                 Closing comment here bracket
				$headers = array('from' => $fromEmail, 'to' => $toEmail, 'subject' => $subject, 'lastName' => $lastName,
					'urlID' => $urlID, 'username' => $username, 'projectName' => $projectName, 'companyName' => $companyName,
					'firstName' => $firstName, 'projectId' => $projectId);
				Mail::send(['html' => $emailTemplate],$headers, function($m) use ($fromEmail, $toEmail, $subject) {
					$m->from($fromEmail);
					$m->to($toEmail)->subject($subject);
				});
				if(!is_null($user['USR_UniqueURLId'])) {
					$project_mostRecent = $user['USR_ProjectMostRecent'];
					$project_previous = $user['USR_ProjectPrevious'];
					$sql = "UPDATE gaig_users.users SET USR_ProjectMostRecent='$projectName-$emailTemplate', USR_ProjectPrevious='$project_mostRecent', USR_ProjectLast='$project_previous' WHERE USR_Username='$username';";
					$updateResult = $db->query($sql);
				}
				else {
					$sql = "UPDATE gaig_users.users SET USR_UniqueURLId='$urlID', USR_ProjectMostRecent='$projectName-$emailTemplate' WHERE USR_Username='$username';";
					$updateResult = $db->query($sql);
				}
				echo "Mail sent to " . $toEmail;
				echo "Unique URL ID generated: " . $urlID . "<br />";
			} else {
				echo "Mail not sent to " . $user['USR_Username'] . "@gaig.com";
				echo "User's last project was " . $user['USR_ProjectMostRecent'] . "<br />";
			}
		}
		$db->close();
	}*/

	/**
	 * sendEmail
	 * Function mapped to Laravel route. Defines variable arrays and calls Email Class executeEmail.
	 *
	 * @param 	Request 		$request			Request object passed via AJAX from client.
	 */
	public function sendEmail(Request $request) {
		try {
			$templateConfig = new TemplateConfiguration(
				array(
					'templateName'=>$request['emailTemplate'],
					'companyName'=>$request['companyName'],
					'projectName'=>$request['projectData']['projectName'],
					'projectId'=>intval($request['projectData']['projectId'])
				)
			);

            $periodInWeeks = 4;
            $users = array();
			$emailConfig = new EmailConfiguration(
				array(
					'host'=>$request['hostName'],
					'port'=>$request['port'],
					'authUsername'=>$request['username'],
					'authPassword'=>$request['password'],
					'fromEmail'=>$request['fromEmail'],
					'subject'=>$request['subject'],
                    'users'=>$templateConfig->getValidUsers($users,$periodInWeeks)
				)
			);

			Email::executeEmail($emailConfig,$templateConfig);
		} catch(ConfigurationException $ce) {
		    //will be doing something here - what still has yet to be defined (likely just log the exception)
		} catch(EmailException $ee) {
            //will be doing something here - what still has yet to be defined (likely just log the exception)
		}
	}

	public function generateEmailForm() {
		if($this->isUserAuth()) {
			try {
				$db = new DBManager();
				$sql = "SELECT DFT_MailServer,DFT_MailPort,DFT_Username,DFT_CompanyName FROM gaig_users.default_emailsettings
				WHERE DFT_UserId=?;";
				$bindings = array(\Session::get('authUserId'));
				$result = $db->query($sql,$bindings);
				$result = $result->fetch(\PDO::FETCH_ASSOC);
				$dft_host = $result['DFT_MailServer'];
				$dft_port = $result['DFT_MailPort'];
				$dft_user = $result['DFT_Username'];
				$dft_company = $result['DFT_CompanyName'];
				$projects = $this->returnAllProjects();
				$templates = $this->returnAllTemplates();
				$varToPass = array('projectSize'=>$projects[0],'data'=>$projects[1],'templateSize'=>$templates[0],'fileNames'=>$templates[1],
					'dft_host'=>$dft_host,'dft_port'=>$dft_port,'dft_user'=>$dft_user,'dft_company'=>$dft_company);
				return view('forms.emailRequirements')->with($varToPass);
			} catch(Exception $e) {
                //caught exception already logged
                //retry? otherwise redirect to user-friendly error view
            }
		} else {
			//not authenticated redirect
			\Session::put('loginRedirect',$_SERVER['REQUEST_URI']);
			return view('auth.loginTest'); //refactor to remove Test
		}
	}

	public function viewAllProjects() {
		if($this->isUserAuth()) {
			$projects = $this->returnAllProjects();
			if(!is_null($projects)) {
				$varToPass = array('projectSize'=>$projects[0],'data'=>$projects[1]);
				return view('displays.showAllProjects')->with($varToPass);
			}
			//return error
		} else {
			\Session::put('loginRedirect',$_SERVER['REQUEST_URI']);
			return view('auth.loginTest');
		}
	}

	private function returnAllProjects() {
		try {
			$db = new DBManager();
			$sql = "SELECT PRJ_ProjectId, PRJ_ProjectName, PRJ_ProjectStatus FROM gaig_users.projects;";
			$bindings = array();
			$projects = $db->query($sql,$bindings);
			$projectIterator = new PDOIterator($projects);
			$data = array();
			$projectSize = 0;
			foreach($projectIterator as $project) {
				$data[] = array(
				    'PRJ_ProjectId'=>$project['PRJ_ProjectId'],
                    'PRJ_ProjectName'=>$project['PRJ_ProjectName'],
                    'PRJ_ProjectStatus'=>$project['PRJ_ProjectStatus']);
				$projectSize++;
			}
			return array($projectSize,$data);
		} catch(Exception $e) {
            //caught exception already logged
            //retry? otherwise redirect to user-friendly error view
        }
		return null;
	}

	public function viewAllTemplates() {
		if($this->isUserAuth()) {
			$templates = $this->returnAllTemplates();
			for($i = 0; $i < $templates[0]; $i++) {
				$filePrefaces[$i] = substr($templates[1][$i],0,3);
				$fileTypes[$i] = substr($templates[1][$i],3,1);
				if($fileTypes[$i] == 'T') {
					$fileTypes[$i] = 'tar';
				} else if($fileTypes[$i] == 'G') {
					$fileTypes[$i] = 'gen';
				} else {
					$fileTypes[$i] = 'edu';
				}
			}
			$varToPass = array('templateSize'=>$templates[0],'fileNames'=>$templates[1],'filePrefaces'=>$filePrefaces,'fileTypes'=>$fileTypes);
			return view('displays.showAllTemplates')->with($varToPass);
		} else {
			\Session::put('loginRedirect',$_SERVER['REQUEST_URI']);
			return view('auth.loginTest');
		}
	}

	private function returnAllTemplates() {
		$files = [];
		$fileNames = [];
		$filesInFolder = \File::files('../resources/views/emails/phishing');
		foreach($filesInFolder as $path) {
			$files[] = pathinfo($path);
		}
		$templateSize = sizeof($files);
		for($i = 0; $i < $templateSize; $i++) {
			$fileNames[$i] = $files[$i]['filename'];
			$fileNames[$i] = substr($fileNames[$i],0,-6);
		}
		return array($templateSize,$fileNames);
	}
	
	public function createNewProject(Request $request) {
		try {
			$db = new DBManager();
			$sql = "INSERT INTO gaig_users.projects (PRJ_ProjectId,PRJ_ProjectName,PRJ_ComplexityType,PRJ_TargetType,
            PRJ_ProjectAssignee,PRJ_ProjectStart,PRJ_ProjectLastActive,PRJ_ProjectStatus,PRJ_ProjectTotalUsers,
            PRJ_EmailViews,PRJ_WebsiteViews,PRJ_ProjectTotalReports) VALUES (null,?,?,?,?,?,?,'Inactive',0,0,0,0);";

			$projectName = $request->input('projectNameText');
			$projectAssignee = $request->input('projectAssigneeText');
            $complexityType = $request->input('projectComplexityType');
            $targetType = $request->input('projectTargetType');
			$date = date("Y-m-d");
			$bindings = array($projectName,$complexityType,$targetType,$projectAssignee,$date,$date);

			$db->query($sql,$bindings);
		} catch(Exception $e) {
            //caught exception already logged
            //retry? otherwise redirect to user-friendly error view
        }
	}

	public function createNewTemplate(Request $request) {
		$path = '../resources/views/emails/';
		$templateName = $request->input('templateName');
		$path = $path . $templateName . '.blade.php';
		$templateContent = $request->input('templateContent');
		\File::put($path,$templateContent);
		\File::delete('../resources/views/emails/.blade.php');
	}

	public function htmlReturner($id) {
		$path = '../resources/views/emails/' . $id . '.blade.php';
		$contents = '';
		try {
			$contents = \File::get($path);
		}
		catch (FileNotFoundException $fnfe) {
			$contents = "Preview Unavailable";
		}
		return $contents;
	}

	public function updateDefaultEmailSettings(Request $request) {
		try {
			$db = new DBManager();

			$username = $request['usernameText'];
			$company = $request['companyText'];
			$host = $request['mailServerText'];
			$port = $request['mailPortText'];
			$userId = \Session::get('authUserId');

			$settingsExist = $this->queryDefaultEmailSettings($userId);

			if($settingsExist->fetchColumn() > 0) {
				$sql = "UPDATE gaig_users.default_emailsettings SET DFT_MailServer=?,DFT_MailPort=?,DFT_Username=?, 
							DFT_CompanyName=? WHERE DFT_UserId=?;";
				$bindings = array($host,$port,$username,$company,$userId);
				$db->query($sql,$bindings);
			} else {
				$sql = "INSERT INTO gaig_users.default_emailsettings (DFT_UserId, DFT_MailServer, DFT_MailPort,
					DFT_Username, DFT_CompanyName) VALUES (?,?,?,?,?);";
				$bindings = array($userId,$host,$port,$username,$company);
				$db->query($sql,$bindings);
			}
			//return something back to ajax
		} catch(Exception $e) {
            //caught exception helper function already logged
            //retry? otherwise do nothing
        }
	}

	public function generateDefaultEmailSettingsForm() {
		if($this->isUserAuth()) {
			try {
				$settingsExist = $this->queryDefaultEmailSettings(\Session::get('authUserId'));
				if($result = $settingsExist->fetch(\PDO::FETCH_ASSOC)) {
					$dft_host = $result['DFT_MailServer'];
					$dft_port = $result['DFT_MailPort'];
					$dft_user = $result['DFT_Username'];
					$dft_company = $result['DFT_CompanyName'];
				} else {
					$dft_host = '';
					$dft_port = '';
					$dft_company = '';
					$dft_user = '';
				}
				$varToPass = array('dft_host'=>$dft_host,'dft_port'=>$dft_port,'dft_user'=>$dft_user,'dft_company'=>$dft_company);
				return view('forms.defaultEmailSettings')->with($varToPass);
			} catch(Exception $e) {
                //caught exception already logged
                //retry? otherwise redirect to user-friendly error view
            }
		} else {
			//not authenticated redirect
			\Session::put('loginRedirect',$_SERVER['REQUEST_URI']);
			return redirect()->to('/auth/login');
		}
	}

	private function queryDefaultEmailSettings($userId) {
		$db = new DBManager();

		$sql = "SELECT COUNT(*) FROM gaig_users.default_emailsettings WHERE DFT_UserId=?;";
		$bindings = array($userId);
		$settingsExist = $db->query($sql,$bindings);
		return $settingsExist;
	}

	public function postLogin(Request $request) {
		try {
			$db = new DBManager();
			$username = $request['usernameText'];
			$password = $request['passwordText'];

			$sql = "SELECT USR_Password,USR_UserId FROM gaig_users.users WHERE USR_Username=?;";
			$bindings = array($username);
			$result = $db->query($sql,$bindings);

			if($result = $result->fetch(\PDO::FETCH_ASSOC)) {
				if(password_verify($password,$result['USR_Password'])) {
					\Session::put('authUser',$username);
					\Session::put('authUserId',$result['USR_UserId']);
					\Session::put('authIp',$_SERVER['REMOTE_ADDR']);

					$redirectPage = \Session::get('loginRedirect');
					if($redirectPage) {
						return redirect()->to($redirectPage);
					} else {
						return view('errors.500');
					}
				} else {
					$varToPass = array('errors'=>array('The password provided does not match our records.'));
					return view('auth.loginTest')->with($varToPass);
				}
			} else {
				$varToPass = array('errors'=>array("We failed to find the username provided. Check your spelling and try 
				again. If this problem continues, contact your manager."));
				return view('auth.loginTest')->with($varToPass);
			}
		} catch(Exception $e) {
            //caught exception already logged
            //retry? otherwise redirect to user-friendly error view
        }
	}

	//disabled until add manager registration of users
	public function postRegister(Request $request) {
		try {
			$db = new DBManager();
			$username = $request['usernameText'];
			$password = $request['passwordText'];
			$firstName = $request['firstNameText'];
			$lastName = $request['lastNameText'];
			$password = password_hash($password,PASSWORD_DEFAULT);
			$sql = "INSERT INTO gaig_users.users (USR_UserId,USR_Username,USR_FirstName,USR_LastName,
				USR_UniqueURLId,USR_Password,USR_ProjectMostRecent,USR_ProjectPrevious,USR_ProjectLast) VALUES
				(null,?,?,?,null,?,null,null,null);";
			$bindings = array($username,$firstName,$lastName,$password);
			$db->query($sql,$bindings);

			$sql = "SELECT USR_UserId FROM gaig_users.users WHERE USR_Username=?;";
			$bindings = array($username);
			$result = $db->query($sql,$bindings);
			$result = $result->fetch(\PDO::FETCH_ASSOC);

			\Session::put('authUser',$username);
			\Session::put('authUserId',$result['USR_UserId']);
			\Session::put('authIp',$_SERVER['REMOTE_ADDR']);
		} catch(Exception $e) {
            //caught exception already logged
            //retry? otherwise redirect to user-friendly error view
        }
	}

	public function changePassword(Request $request) {
        if($this->isUserAuth()) {
            try {
                $db = new DBManager();
                $passwordOld = $request['passwordOldText'];
                $username = \Session::get('authUser');

                $sql = "SELECT USR_Password,USR_UserId FROM gaig_users.users WHERE USR_Username=?;";
                $bindings = array($username);
                $result = $db->query($sql,$bindings);

                if($result = $result->fetch(\PDO::FETCH_ASSOC)) {
                    if(password_verify($passwordOld,$result['USR_Password'])) {
                        $passwordNew = password_hash($request['passwordNewText'],PASSWORD_DEFAULT);

                        $sql = "UPDATE gaig_users.users SET USR_Password=? WHERE USR_Username=?;";
                        $bindings = array($passwordNew,$username);
                        $db->query($sql,$bindings);
                    } else {
                        $varToPass = array('errors'=>array('The password provided does not match our records.'));
                        //return view('auth.loginTest')->with($varToPass);
                    }
                }
            } catch(Exception $e) {

            }
        }
    }

	public function logout() {
		\Session::forget('authUser');
		\Session::forget('authUserId');
		\Session::forget('loginRedirect');
		\Session::forget('authIp');
		return redirect()->to('http://localhost:8888');
	}

	public function postWebsiteJson() {
		if($this->isUserAuth()) {
			try {
				$db = new DBManager();
				$sql = "SELECT WBS_Ip,WBS_Host,WBS_ReqPath,WBS_Username,WBS_ProjectName,WBS_AccessTimestamp 
						FROM gaig_users.website_tracking;";
				$json = $db->query($sql,array());
				$jsonIterator = new PDOIterator($json);
				$websiteData = array();
				foreach($jsonIterator as $data) {
					$websiteData[] = array('WBS_Ip'=>$data[0],'WBS_Host'=>$data[1],
						'WBS_ReqPath'=>$data[2],'WBS_Username'=>$data[3],
						'WBS_ProjectName'=>$data[4],'WBS_AccessTimestamp'=>$data[5]);
				}
				return $websiteData;
			} catch(Exception $e) {
                //caught exception already logged
                //retry? otherwise redirect to error view
            }
		}
		return view('errors.401');
	}

	public function postEmailJson() {
		if($this->isUserAuth()) {
			try {
				$db = new DBManager();
				$sql = "SELECT EML_Ip,EML_Host,EML_Username,EML_ProjectName,EML_AccessTimestamp
 						FROM gaig_users.email_tracking;";
				$json = $db->query($sql,array());
				$jsonIterator = new PDOIterator($json);
				$emailData = array();
				foreach($jsonIterator as $data) {
					$emailData[] = array('EML_Ip'=>$data[0],'EML_Host'=>$data[1],
						'EML_Username'=>$data[2],'EML_ProjectName'=>$data[3],'WBS_AccessTimestamp'=>$data[4]);
				}
				return $emailData;
			} catch(Exception $e) {
                //caught exception already logged
                //retry? otherwise redirect to error view
            }
		}
		return view('errors.401');
	}

	public function postReportsJson() {
		if($this->isUserAuth()) {
			try {
				$db = new DBManager();
				$sql = "SELECT EML_Ip,EML_Host,EML_Username,EML_ProjectName,EML_AccessTimestamp
 						FROM gaig_users.email_tracking;";
				$json = $db->query($sql,array());
				$jsonIterator = new PDOIterator($json);
				$reportData = array();
				foreach($jsonIterator as $data) {
					$reportData[] = array('RPT_EmailSubject'=>$data[0],'RPT_UserEmail'=>$data[1],
						'RPT_OriginalFrom'=>$data[2],'RPT_ReportDate'=>$data[3]);
				}
				return $reportData;
			} catch(Exception $e) {
                //caught exception already logged
                //retry? otherwise redirect to error view
            }
		}
		return view('errors.401');
	}

	private function isUserAuth() {
		return \Session::get('authUserId') && \Session::get('authIp') == $_SERVER['REMOTE_ADDR'];
	}
}
