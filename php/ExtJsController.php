<?php
/**
 * Ext JS controller class definition file
 *
 * @todo Add inline documentation for each controller task
 */

require_once dirname(__FILE__) . '/LockFile.php';
require_once dirname(__FILE__) . '/BugReader.php';
require_once dirname(__FILE__) . '/NewsReader.php';
require_once dirname(__FILE__) . '/JsonResponseBuilder.php';
require_once dirname(__FILE__) . '/AccountManager.php';
require_once dirname(__FILE__) . '/File.php';
require_once dirname(__FILE__) . '/RepositoryManager.php';
require_once dirname(__FILE__) . '/RepositoryFetcher.php';
require_once dirname(__FILE__) . '/LogManager.php';
require_once dirname(__FILE__) . '/VCSFactory.php';
require_once dirname(__FILE__) . '/TranslationStatistic.php';
require_once dirname(__FILE__) . '/TranslatorStatistic.php';

/**
 * Ext JS controller class
 */
class ExtJsController
{
    /**
     * Array of request variables
     *
     * @var array
     */
    private $requestVariables = array();

    /**
     * Initializes the controller
     *
     * @param array $request An associative array of request variables
     */
    public function __construct($request)
    {
        $this->requestVariables = $request;
    }

    /**
     * Gets the specified request variable
     *
     * @param string $name The variable name
     * @return mixed The variable value on success, FALSE is the variable was not set
     */
    public function getRequestVariable($name)
    {
        return $this->hasRequestVariable($name)
                ? $this->requestVariables[$name]
                : false;
    }

    /**
     * Tells if the specified request variable exist
     *
     * @param string $name The variable name
     * @return mixed Returns TRUE if the variable exists, FALSE otherwise
     */
    public function hasRequestVariable($name)
    {
        return isset($this->requestVariables[$name]);
    }

    /**
     * Login to the tool
     *
     * @return The Success response on success, or a Failure
     */
    public function login()
    {
        $vcsLogin  = $this->getRequestVariable('vcsLogin');
        $vcsPasswd = $this->getRequestVariable('vcsPassword');
        $lang      = $this->getRequestVariable('lang');

        $response = AccountManager::getInstance()->login($vcsLogin, $vcsPasswd, $lang);

        if ($response['state'] === true) {
            // This user is already know in a valid user
            return JsonResponseBuilder::success();
        } elseif ($response['state'] === false) {
            // This user is unknow from this server
            return JsonResponseBuilder::failure(array('msg' => $response['msg']));
        } else {
            return JsonResponseBuilder::failure();
        }
    }

    public function updateRepository()
    {
        AccountManager::getInstance()->isLogged();

        if (AccountManager::getInstance()->vcsLogin == 'anonymous') {
            return JsonResponseBuilder::failure();
        }

        RepositoryManager::getInstance()->updateRepository();
        return JsonResponseBuilder::success();
    }

    public function checkLockFile()
    {
        $lockFile = $this->getRequestVariable('lockFile');
        $lock     = new LockFile($lockFile);

        AccountManager::getInstance()->isLogged();
        return $lock->isLocked()
            ? JsonResponseBuilder::success()
            : JsonResponseBuilder::failure();
    }

    public function applyTools()
    {
        AccountManager::getInstance()->isLogged();

        $rm = RepositoryManager::getInstance();

        $rm->cleanUp();

        // Set the lock File
        $lock = new LockFile('lock_apply_tools');

        if ($lock->lock()) {

            // Start Revcheck
            $rm->applyRevCheck();

            // Search for NotInEN Old Files
            $rm->updateNotInEN();

            // Parse translators
            $rm->updateTranslatorInfo();

            // Set lastUpdate date/time
            $rm->setLastUpdate();
        }
        $lock->release();

        return JsonResponseBuilder::success();
    }

    /**
     * Pings the server and user session
     *
     * @return string "pong" on success, "false" on failure
     */
    public function ping()
    {
        AccountManager::getInstance()->isLogged();
        $r = RepositoryFetcher::getInstance()->getLastUpdate();

        $response = !isset($_SESSION['userID']) ? 'false' : 'pong';

        return JsonResponseBuilder::success(
            array(
                'ping'       => $response,
                'lastupdate' => $r['lastupdate'],
                'by'         => $r['by']
            )
        );
    }

    public function getFilesNeedUpdate()
    {
        AccountManager::getInstance()->isLogged();
        $r = RepositoryFetcher::getInstance()->getPendingUpdate();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => $r['nb'],
                'Items'   => $r['node']
            )
        );
    }

    public function getFilesNeedTranslate()
    {
        AccountManager::getInstance()->isLogged();
        $r = RepositoryFetcher::getInstance()->getPendingTranslate();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => $r['nb'],
                'Items'   => $r['node']
            )
        );
    }

    public function getFilesNotInEn()
    {
        AccountManager::getInstance()->isLogged();
        $r = RepositoryFetcher::getInstance()->getNotInEn();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => $r['nb'],
                'Items'   => $r['node']
            )
        );
    }

    public function getFilesNeedReviewed()
    {
        AccountManager::getInstance()->isLogged();
        $r = RepositoryFetcher::getInstance()->getPendingReview();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => $r['nb'],
                'Items'   => $r['node']
            )
        );
    }

    public function getFilesError()
    {
        AccountManager::getInstance()->isLogged();

        $errorTools = new ToolsError();
        $errorTools->setParams('', '', AccountManager::getInstance()->vcsLang, '', '', '');
        $r = $errorTools->getFilesError(RepositoryFetcher::getInstance()->getModifies());

        return JsonResponseBuilder::success(
            array(
                'nbItems' => $r['nb'],
                'Items'   => $r['node']
            )
        );
    }

    public function getFilesPendingCommit()
    {
        AccountManager::getInstance()->isLogged();

        $r = RepositoryFetcher::getInstance()->getPendingCommit();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => $r['nb'],
                'Items'   => $r['node']
            )
        );
    }

    public function getFilesPendingPatch()
    {
        AccountManager::getInstance()->isLogged();

        $r = RepositoryFetcher::getInstance()->getPendingPatch();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => $r['nb'],
                'Items'   => $r['node']
            )
        );
    }

    public function getTranslatorInfo()
    {
        AccountManager::getInstance()->isLogged();

        $translators = TranslatorStatistic::getInstance()->getSummary();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => count($translators),
                'Items'   => $translators
            )
        );
    }

    public function getSummaryInfo()
    {
        AccountManager::getInstance()->isLogged();

        $summary = TranslationStatistic::getInstance()->getSummary();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => count($summary),
                'Items'   => $summary
            )
        );
    }

    public function getLastNews()
    {
        AccountManager::getInstance()->isLogged();

        $nr = new NewsReader(AccountManager::getInstance()->vcsLang);
        $r  = $nr->getLastNews();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => count($r),
                'Items'   => $r
            )
        );
    }

    public function getOpenBugs()
    {
        AccountManager::getInstance()->isLogged();

        $bugs = new BugReader(AccountManager::getInstance()->vcsLang);
        $r = $bugs->getOpenBugs();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => count($r),
                'Items'   => $r
            )
        );
    }

    public function getFile()
    {
        AccountManager::getInstance()->isLogged();

        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');

        $readOriginal = $this->hasRequestVariable('readOriginal')
                        ? $this->getRequestVariable('readOriginal')
                        : false;

        $t = explode('/', $FilePath);
        $FileLang = array_shift($t);
        $FilePath = implode('/', $t);

        // We must detect the encoding of the file with the first line "xml version="1.0" encoding="utf-8"
        // If this utf-8, we don't need to use utf8_encode to pass to this app, else, we apply it

        $file     = new File($FileLang, $FilePath, $FileName);
        $content  = $file->read($readOriginal);
        $encoding = $file->getEncoding($content);

        $return = array();
        if (strtoupper($encoding) == 'UTF-8') {
            $return['content'] = $content;
        } else {
            $return['content'] = iconv($encoding, "UTF-8", $content);
        }

        return JsonResponseBuilder::success($return);
    }

    public function checkFileError()
    {
        AccountManager::getInstance()->isLogged();
        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');
        $FileLang = $this->getRequestVariable('FileLang');

        // Remove \
        $FileContent = stripslashes($this->getRequestVariable('FileContent'));

        // Replace &nbsp; by space
        $FileContent = str_replace("&nbsp;", "", $FileContent);

        $file = new File($FileLang, $FilePath, $FileName);
        // Detect encoding
        $charset = $file->getEncoding($FileContent);

        // If the new charset is set to utf-8, we don't need to decode it
        if ($charset != 'utf-8') {
            // Utf8_decode
            $FileContent = utf8_decode($FileContent);
        }

        // Get EN content to check error with
        $en_file    = new File('en', $FilePath, $FileName);
        $en_content = $en_file->read();

        // Update DB with this new Error (if any)
        $info = $file->getInfo($FileContent);
        $anode[0] = array(
            'lang'         => $FileLang,
            'path'         => $FilePath,
            'name'         => $FileName,
            'en_content'   => $en_content,
            'lang_content' => $FileContent,
            'maintainer'   => $info['maintainer']
        );

        $errorTools = new ToolsError();
        $r = $errorTools->updateFilesError($anode, 'nocommit');

        return JsonResponseBuilder::success(
            array(
                'error'       => $r['state'],
                'error_first' => $r['first']
            )
        );
    }

    public function saveFile()
    {
        AccountManager::getInstance()->isLogged();


        $filePath   = $this->getRequestVariable('filePath');
        $fileName   = $this->getRequestVariable('fileName');
        $fileLang   = $this->getRequestVariable('fileLang');
        $type       = $this->hasRequestVariable('type')
                        ? $this->getRequestVariable('type')
                        : 'file';
        $emailAlert = $this->hasRequestVariable('emailAlert')
                        ? $this->getRequestVariable('emailAlert')
                        : '';

        if (AccountManager::getInstance()->vcsLogin == 'anonymous' && ($type == 'file' || $type == 'trans')) {
            return JsonResponseBuilder::failure();
        }

        // Clean up path
        $filePath = str_replace('//', '/', $filePath);

        // Extract lang from path
        if ($fileLang == 'all') {
            $t = explode('/', $filePath);

            $fileLang = $t[0];

            array_shift($t);
            $filePath = '/'.implode('/', $t);
        }

        // Remove \
        $fileContent = stripslashes($this->getRequestVariable('fileContent'));

        // Replace &nbsp; by space
        $fileContent = str_replace("&nbsp;", "", $fileContent);

        // Detect encoding
        $file = new File($fileLang, $filePath, $fileName);
        $charset = $file->getEncoding($fileContent);

        // If the new charset is set to utf-8, we don't need to decode it
        if ($charset != 'utf-8') {
            // Utf8_decode
            //$fileContent = utf8_decode($fileContent);
            $fileContent = iconv("UTF-8", $charset, $fileContent);
        }

        // Get revision
        $info = $file->getInfo($fileContent);

        if ($type == 'file') {

            $file->save($fileContent, false);
            $r = RepositoryManager::getInstance()->addPendingCommit(
                $file, $info['rev'], $info['en-rev'], $info['reviewed'], $info['maintainer']
            );
            return JsonResponseBuilder::success(
                array(
                    'id'           => $r,
                    'en_revision'  => $info['rev'],
                    'new_revision' => $info['en-rev'],
                    'maintainer'   => $info['maintainer'],
                    'reviewed'     => $info['reviewed']
                )
            );
        } else if ($type == 'trans') {

            // We must to ensure that this folder existe in the VCS repository & localy
            $vf = VCSFactory::getInstance();

            if( $vf->folderExist($file) ) {

               $file->save($fileContent, false);
               $r = RepositoryManager::getInstance()->addPendingCommit(
                   $file, $info['rev'], $info['en-rev'], $info['reviewed'], $info['maintainer'], 'new'
               );
               return JsonResponseBuilder::success(
                   array(
                       'id'           => $r,
                       'en_revision'  => $info['rev'],
                       'new_revision' => $info['en-rev'],
                       'maintainer'   => $info['maintainer'],
                       'reviewed'     => $info['reviewed']
                   )
               );

            } else {
              return JsonResponseBuilder::failure();
            }

        } else {

            $uniqID = RepositoryManager::getInstance()->addPendingPatch(
                $file, $emailAlert
            );
            $file->save($fileContent, true, $uniqID);

            return JsonResponseBuilder::success(
                array(
                    'uniqId' => $uniqID
                )
            );
        }
    }

    public function getLog()
    {
        AccountManager::getInstance()->isLogged();
        $Path = $this->getRequestVariable('Path');
        $File = $this->getRequestVariable('File');

        $r = VCSFactory::getInstance()->log($Path, $File);

        return JsonResponseBuilder::success(
            array(
                'nbItems' => count($r),
                'Items'   => $r
            )
        );
    }

    public function getDiff()
    {
        AccountManager::getInstance()->isLogged();
        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');

        $t = explode('/', $FilePath);
        $FileLang = array_shift($t);
        $FilePath = implode('/', $t);

        $type     = $this->hasRequestVariable('type')
                    ? $this->getRequestVariable('type')
                    : '';
        $uniqID   = $this->hasRequestVariable('uniqID')
                    ? $this->getRequestVariable('uniqID')
                    : '';

        $file = new File($FileLang, $FilePath, $FileName);
        $info = $file->htmlDiff(($type=='patch'), $uniqID);

        return JsonResponseBuilder::success(
            array(
                'content'  => $info['content'],
                'encoding' => $info['charset']
            )
        );
    }

    public function getDiff2()
    {
        AccountManager::getInstance()->isLogged();
        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');

        $t = explode('/', $FilePath);
        $FileLang = array_shift($t);
        $FilePath = implode('/', $t);

        $Rev1 = $this->getRequestVariable('Rev1');
        $Rev2 = $this->getRequestVariable('Rev2');

        $file = new File($FileLang, $FilePath, $FileName);
        $r = $file->vcsDiff($Rev1, $Rev2);

        return JsonResponseBuilder::success(
            array(
                 'content' => $r
            )
        );
    }

    public function erasePersonalData()
    {
        AccountManager::getInstance()->isLogged();

        if (AccountManager::getInstance()->vcsLogin == 'anonymous') {
            return JsonResponseBuilder::failure();
        }

        AccountManager::getInstance()->eraseData();

        return JsonResponseBuilder::success();
    }

    public function getCommitLogMessage()
    {
        AccountManager::getInstance()->isLogged();
        $r = LogManager::getInstance()->getCommitLog();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => count($r),
                'Items'   => $r
            )
        );
    }

    public function clearLocalChange()
    {
        AccountManager::getInstance()->isLogged();

        if (AccountManager::getInstance()->vcsLogin == 'anonymous') {
            return JsonResponseBuilder::failure();
        }

        $FileType = $this->getRequestVariable('FileType');
        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');

        $t = explode('/', $FilePath);
        $FileLang = array_shift($t);
        $FilePath = implode('/', $t);

        $info = RepositoryManager::getInstance()->clearLocalChange(
            $FileType, new File($FileLang, $FilePath, $FileName)
        );

        return JsonResponseBuilder::success(
            array(
                'revision'   => $info['rev'],
                'maintainer' => $info['maintainer'],
                'error'      => $info['errorFirst'],
                'reviewed'   => $info['reviewed']
            )
        );
    }

    public function getLogFile()
    {
        AccountManager::getInstance()->isLogged();

        $file = $this->getRequestVariable('file');

        $content = LogManager::getInstance()->readOutputLog($file);

        return JsonResponseBuilder::success(
            array(
                'mess' => $content
            )
        );
    }

    public function checkBuild()
    {
        AccountManager::getInstance()->isLogged();

        if (AccountManager::getInstance()->vcsLogin == 'anonymous') {
            return JsonResponseBuilder::failure();
        }

        $xmlDetails = $this->getRequestVariable('xmlDetails');

        $lock = new LockFile('lock_check_build');
        if ($lock->lock()) {

            // Start the checkBuild system
            $output = RepositoryManager::getInstance()->checkBuild($xmlDetails);
        }
        // Remove the lock File
        $lock->release();

        // Send output into a log file
        LogManager::getInstance()->saveOutputLog('log_check_build', $output);

        return JsonResponseBuilder::success();
    }

    public function vcsCommit()
    {
        AccountManager::getInstance()->isLogged();

        if (AccountManager::getInstance()->vcsLogin == 'anonymous') {
            return JsonResponseBuilder::failure();
        }

        $nodes = $this->getRequestVariable('nodes');
        $logMessage = stripslashes($this->getRequestVariable('logMessage'));

        $anode = json_decode(stripslashes($nodes));

        $r = RepositoryManager::getInstance()->commitChanges($anode, $logMessage);

        return JsonResponseBuilder::success(
            array(
                'mess' => $r
            )
        );
    }

    public function onSuccesCommit()
    {
        AccountManager::getInstance()->isLogged();

        if (AccountManager::getInstance()->vcsLogin == 'anonymous') {
            return JsonResponseBuilder::failure();
        }

        $nodes = $this->getRequestVariable('nodes');
        $logMessage = stripslashes($this->getRequestVariable('logMessage'));

        $anode = json_decode(stripslashes($nodes));

        $nodes = RepositoryFetcher::getInstance()->getModifiesById($anode);

        // We need to provide a different treatment regarding the file's type...
        $existFiles = array(); // Can be an updated file or a new file
        $deleteFiles = array();
        $j = 0;

        for ($i = 0; $i < count($nodes); $i++) {

            if( $nodes[$i]['type'] == 'update' || $nodes[$i]['type'] == 'new' ) {
                $existFiles[] = new File(
                    $nodes[$i]['lang'],
                    $nodes[$i]['path'],
                    $nodes[$i]['name']
                );
            }

            if( $nodes[$i]['type'] == 'delete' ) {
                $deleteFiles[$j]->lang = $nodes[$i]['lang'];
                $deleteFiles[$j]->path = $nodes[$i]['path'];
                $deleteFiles[$j]->name = $nodes[$i]['name'];
                $j ++;
            }

        }

        // ... for existing Files (new or update)
        if( !empty($existFiles) ) {

            // Update revision & reviewed for all this files (LANG & EN)
            RepositoryManager::getInstance()->updateFileInfo($existFiles);

            // Stuff only for LANG files
            $langFiles = array();
            $j = 0;

            for ($i = 0; $i < count($existFiles); $i++) {
                // Only for lang files.
                if( $existFiles[$i]->lang != 'en' ) {

                    $en = new File('en', $existFiles[$i]->path, $existFiles[$i]->name);

                    $info = $existFiles[$i]->getInfo();

                    $langFiles[$j]['en_content']   = $en->read(true);
                    $langFiles[$j]['lang_content'] = $existFiles[$i]->read(true);
                    $langFiles[$j]['lang'] = $existFiles[$i]->lang;
                    $langFiles[$j]['path'] = $existFiles[$i]->path;
                    $langFiles[$j]['name'] = $existFiles[$i]->name;
                    $langFiles[$j]['maintainer'] = $info['maintainer'];

                    $j ++;
                }
            }
            if( !empty($langFiles) ) {
                $errorTools = new ToolsError();
                $errorTools->updateFilesError($langFiles);
            }
            // Remove all this files in needcommit
            RepositoryManager::getInstance()->delPendingCommit($existFiles);
        } // End of $existFiles stuff

        // ... for deleted Files
        if( !empty($deleteFiles) ) {

            // Remove this files from the repository
            RepositoryManager::getInstance()->delFiles($deleteFiles);

            // Remove all this files in needcommit
            RepositoryManager::getInstance()->delPendingCommit($deleteFiles);

        } // End of $deleteFiles stuff

        // Manage log message (add new or ignore it if this message already exist for this user)
        LogManager::getInstance()->addCommitLog($logMessage);

        return JsonResponseBuilder::success();
    }

    public function getConf()
    {
        AccountManager::getInstance()->isLogged();

        $r = array();
        $r['userLang']  = AccountManager::getInstance()->vcsLang;
        $r['userLogin'] = AccountManager::getInstance()->vcsLogin;
        $r['userConf']  = AccountManager::getInstance()->userConf;

        return JsonResponseBuilder::success(
            array(
                'mess' => $r
            )
        );
    }

    public function sendEmail()
    {
        AccountManager::getInstance()->isLogged();

        $to      = $this->getRequestVariable('to');
        $subject = $this->getRequestVariable('subject');
        $msg     = $this->getRequestVariable('msg');

        AccountManager::getInstance()->email($to, $subject, $msg);

        return JsonResponseBuilder::success();
    }

    public function confUpdate()
    {
        AccountManager::getInstance()->isLogged();

        $item      = $this->getRequestVariable('item');
        $value     = $this->getRequestVariable('value');

        AccountManager::getInstance()->updateConf($item, $value);

        return JsonResponseBuilder::success();
    }

    public function getAllFiles()
    {
        AccountManager::getInstance()->isLogged();

        $node   = $this->getRequestVariable('node');
        $search = $this->getRequestVariable('search');

        if ($this->hasRequestVariable('search')) {
            $files = RepositoryFetcher::getInstance()->getFileByKeyword($search);
        } else {
            $files = RepositoryFetcher::getInstance()->getFilesByDirectory($node);
        }

// for extjs.TreeLoader, Loader must accept TreeNode objects only
        return JsonResponseBuilder::response($files);
/*
        return JsonResponseBuilder::success($files);
*/
    }

    public function saveLogMessage()
    {
        AccountManager::getInstance()->isLogged();

        if (AccountManager::getInstance()->vcsLogin == 'anonymous') {
            return JsonResponseBuilder::failure();
        }

        $messID = $this->getRequestVariable('messID');
        $mess   = stripslashes($this->getRequestVariable('mess'));

        LogManager::getInstance()->updateCommitLog($messID, $mess);

        return JsonResponseBuilder::success();
    }

    public function deleteLogMessage()
    {
        AccountManager::getInstance()->isLogged();

        if (AccountManager::getInstance()->vcsLogin == 'anonymous') {
            return JsonResponseBuilder::failure();
        }

        $messID = $this->getRequestVariable('messID');

        LogManager::getInstance()->delCommitLog($messID);

        return JsonResponseBuilder::success();
    }

    public function getAllFilesAboutExtension()
    {
        AccountManager::getInstance()->isLogged();

        $ExtName = $this->getRequestVariable('ExtName');

        $r = RepositoryFetcher::getInstance()->getFilesByExtension($ExtName);

        return JsonResponseBuilder::success(
            array(
                'files' => $r
            )
        );
    }

    public function afterPatchAccept()
    {
        AccountManager::getInstance()->isLogged();

        $PatchUniqID = $this->getRequestVariable('PatchUniqID');

        RepositoryManager::getInstance()->postPatchAccept($PatchUniqID);

        return JsonResponseBuilder::success();
    }

    public function afterPatchReject()
    {
        AccountManager::getInstance()->isLogged();

        if (AccountManager::getInstance()->vcsLogin == 'anonymous') {
            return JsonResponseBuilder::failure();
        }

        $PatchUniqID = $this->getRequestVariable('PatchUniqID');

        RepositoryManager::getInstance()->postPatchReject($PatchUniqID);

        return JsonResponseBuilder::success();
    }

    public function getCheckDocData()
    {
        AccountManager::getInstance()->isLogged();

        $ToolsCheckDoc = new ToolsCheckDoc();
        $r = $ToolsCheckDoc->getCheckDocData();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => $r['nb'],
                'Items'   => $r['node']
            )
        );
    }

    public function getBuildStatusData()
    {
        AccountManager::getInstance()->isLogged();

        $r = LogManager::getInstance()->getBuildLogStatus();

        return JsonResponseBuilder::success(
            array(
                'nbItems' => $r['nb'],
                'Items'   => $r['node']
            )
        );
    }

    public function getCheckDocFiles()
    {
        AccountManager::getInstance()->isLogged();

        $path      = $this->getRequestVariable('path');
        $errorType = $this->getRequestVariable('errorType');

        $ToolsCheckDoc = new ToolsCheckDoc();
        $r = $ToolsCheckDoc->getCheckDocFiles($path, $errorType);

        return JsonResponseBuilder::success(
            array(
                'files' => $r
            )
        );
    }

    public function downloadPatch()
    {
        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');

        $t = explode('/', $FilePath);
        $FileLang = array_shift($t);
        $FilePath = implode('/', $t);

        $file  = new File($FileLang, $FilePath, $FileName);
        $patch = $file->rawDiff(false);

        $name = 'patch-' . time() . '.patch';

        $size = strlen($patch);

        header("Content-Type: application/force-download; name=\"$name\"");
        header("Content-Transfer-Encoding: binary");
        header("Content-Disposition: attachment; filename=\"$name\"");
        header("Expires: 0");
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");

        return $patch;
    }

    public function logout()
    {
        $_SESSION = array();
        setcookie(session_name(), '', time()-42000, '/');
        session_destroy();
        header("Location: ../");
        exit;
    }

    public function translationGraph()
    {
        require_once './jpgraph/src/jpgraph.php';
        require_once './jpgraph/src/jpgraph_pie.php';
        require_once './jpgraph/src/jpgraph_pie3d.php';

        AccountManager::getInstance()->isLogged();

        $Total_files_lang = TranslationStatistic::getInstance()->getFileCount();
        $Total_files_lang = $Total_files_lang[0];
        //
        $up_to_date = TranslationStatistic::getInstance()->getTransFileCount();
        $up_to_date = $up_to_date[0];
        //
        $critical = TranslationStatistic::getInstance()->getCriticalFileCount();
        $critical = $critical[0];
        //
        $old = TranslationStatistic::getInstance()->getOldFileCount();
        $old = $old[0];
        //
        $missing = sizeof(TranslationStatistic::getInstance()->getMissedFileCount());
        //
        $no_tag = TranslationStatistic::getInstance()->getNoTagFileCount();
        $no_tag = $no_tag[0];
        //
        $data     = array($up_to_date,$critical,$old,$missing,$no_tag);
        $pourcent = array();
        $total    = 0;
        $total    = array_sum($data);

        foreach ( $data as $valeur ) {
            $pourcent[] = round($valeur * 100 / $total);
        }

        $noExplode = ($Total_files_lang == $up_to_date) ? 1 : 0;

        $legend = array(
            $pourcent[0] . '%% up to date ('.$up_to_date.')',
            $pourcent[1] . '%% critical ('.$critical.')',
            $pourcent[2] . '%% old ('.$old.')',
            $pourcent[3] . '%% missing ('.$missing.')',
            $pourcent[4] . '%% without revtag ('.$no_tag.')'
        );

        $title = 'PHP : Details for '.ucfirst(AccountManager::getInstance()->vcsLang).' Documentation';

        $graph = new PieGraph(530,300);
        $graph->SetShadow();

        $graph->title->Set($title);
        $graph->title->Align('left');
        $graph->title->SetFont(FF_FONT1,FS_BOLD);

        $graph->legend->Pos(0.02,0.18,"right","center");

        $graph->subtitle->Set('(Total: '.$Total_files_lang.' files)');
        $graph->subtitle->Align('left');
        $graph->subtitle->SetColor('darkred');

        $t1 = new Text(date('m/d/Y'));
        $t1->SetPos(522,294);
        $t1->SetFont(FF_FONT1,FS_NORMAL);
        $t1->Align("right", 'bottom');
        $t1->SetColor("black");
        $graph->AddText($t1);

        $p1 = new PiePlot3D($data);
        $p1->SetSliceColors(
            array(
                '#68d888',
                '#ff6347',
                '#eee8aa',
                '#dcdcdc',
                '#f4a460'
            )
        );
        if ($noExplode != 1) {
            $p1->ExplodeAll();
        }
        $p1->SetCenter(0.35,0.55);
        $p1->value->Show(false);

        $p1->SetLegends($legend);

        $graph->Add($p1);
        $graph->Stroke();

        return '';
    }

    public function getLastUpdate()
    {
        AccountManager::getInstance()->isLogged();
        $r = RepositoryFetcher::getInstance()->getLastUpdate();

        return JsonResponseBuilder::success(
            array(
                'success'    => true,
                'lastupdate' => $r['lastupdate']
            )
        );
    }

    public function markAsNeedDelete()
    {
        AccountManager::getInstance()->isLogged();

        $FilePath = $this->getRequestVariable('FilePath');
        $FileName = $this->getRequestVariable('FileName');

        $t = explode('/', $FilePath);
        $FileLang = array_shift($t);
        $FilePath = implode('/', $t);

        $file = new File($FileLang, $FilePath, $FileName);

        $r = RepositoryManager::getInstance()->addPendingDelete($file);

        return JsonResponseBuilder::success(
            array(
                'id'   => $r['id'],
                'by'   => $r['by'],
                'date' => $r['date']
            )
        );
    }

}
