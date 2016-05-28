<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/**
 * Simple Ajax Uploader
 * Version 1.10.1
 * https://github.com/LPology/Simple-Ajax-Uploader
 *
 * Copyright 2013 LPology, LLC
 * Released under the MIT license
 *
 * https://www.lpology.com/code/ajaxuploader/phpdocs.php
 *
 *---------------------------------------------------------------------------------------------------
 * [NEXTLOOP - MODIICATIONS]
 * 26 July 2014 - added codeigniter variable and instance
 * 26 July 2014 - added ability to specify input file name: $this->data['field_name'] = 'myavatar';
 * 31 July 2014 - changed the checkExtension() function to allow option of ALL FILES
 *
 *---------------------------------------------------------------------------------------------------
 * [NEXTLOOP - EXAMPLE USAGE]
 * 
 * //start the upload
 * $this->data['field_name'] = 'myavatar'; //set the input field name (default: 'myfile')
 * $this->load->library('fileupload');
 * $this->fileupload->sizeLimit = 10485760; //(optional)
 * $this->fileupload->newFileName = 'newFile.jpg'; //(optional)
 * $this->fileupload->allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');//optional and if set to empty array() it will mean ALL FILES allowed
 * $result = $this->fileupload->handleUpload(FILES_PROJECT_FOLDER);

 * //some data about new file
 * $filedata['upload_errors'] = $this->fileupload->getErrorMsg();
 * $filedata['file_size'] = $this->fileupload->getFileSize();
 * $filedata['file_name'] = $this->fileupload->getFileName();
 * $filedata['file_extension'] = $this->fileupload->getExtension();
 * $filedata['file_path'] = $this->fileupload->getSavedFile();
 * 
 * if($result){
 * //do something, all is ok
 * }else{
 * //do something, upload has failed
 * }
 * 
 *------------------------------------------------------------------
 *
 */

/**
 * Handles XHR uploads
 * Used by FileUpload below -- don't call this class directly.
 */
class FileUploadXHR
{
    public $uploadName;

    public function Save($savePath)
    {
        if (false !== file_put_contents($savePath, fopen('php://input', 'r'))) {
            return true;
        }
        return false;
    }

    public function getFileName()
    {
        return $_GET[$this->uploadName];
    }

    public function getFileSize()
    {
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            return (int)$_SERVER['CONTENT_LENGTH'];
        } else {
            throw new Exception('Content length not supported.');
        }
    }
}

/**
 * Handles form uploads through hidden iframe
 * Used by FileUpload below -- don't call this class directly.
 */
class FileUploadPOSTForm
{
    public $uploadName;

    public function Save($savePath)
    {
        if (move_uploaded_file($_FILES[$this->uploadName]['tmp_name'], $savePath)) {
            return true;
        }
        return false;
    }

    public function getFileName()
    {
        return $_FILES[$this->uploadName]['name'];
    }

    public function getFileSize()
    {
        return $_FILES[$this->uploadName]['size'];
    }
}

/**
 * Main class for handling file uploads
 */
class FileUpload
{
    public $uploadDir; // File upload directory (include trailing slash)
    public $allowedExtensions; // Array of permitted file extensions
    public $sizeLimit = 1048576000; // Max file upload size in bytes (default 10MB)
    public $newFileName; // Optionally save uploaded files with a new name by setting this
    public $corsInputName = 'XHR_CORS_TARGETORIGIN';
    private $fileName; // Filename of the uploaded file
    private $fileSize; // Size of uploaded file in bytes
    private $fileExtension; // File extension of uploaded file
    private $savedFile; // Path to newly uploaded file (after upload completed)
    private $errorMsg; // Error message if handleUpload() returns false (use getErrorMsg() to retrieve)
    private $handler;
    public $ci;
    /** NEXTLOOP Codeigniter Variable - July 2014*/

    function __construct($uploadName = 'myfile')
    {

        /** -------------------------------------------------------------/
         *  NEXTLOOP - ADD CODEIGNITER INSTANCE JULY 2014
         *  Added ability to pass the <input> field 'name' 
         *  $this->data['uploadName'] = 'some_name'; //in controller
         *
         * --------------------------------------------------------------*/
        $this->ci = &get_instance();
        $uploadName = ($this->ci->data['field_name'] == '') ? $uploadName : $this->ci->data['field_name'];

        if (isset($_FILES[$uploadName])) {
            $this->handler = new FileUploadPOSTForm(); // Form-based upload
        } elseif (isset($_GET[$uploadName])) {
            $this->handler = new FileUploadXHR(); // XHR upload
        } else {
            $this->handler = false;
        }

        if ($this->handler) {
            $this->handler->uploadName = $uploadName;
            $this->fileName = $this->handler->getFileName();
            $this->fileSize = $this->handler->getFileSize();
            $fileInfo = pathinfo($this->fileName);
            if (array_key_exists('extension', $fileInfo)) {
                $this->fileExtension = strtolower($fileInfo['extension']);
            }
        }
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    public function getFileSize()
    {
        return $this->fileSize;
    }

    public function getExtension()
    {
        return $this->fileExtension;
    }

    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    public function getSavedFile()
    {
        return $this->savedFile;
    }

    private function checkExtension($ext, $allowedExtensions)
    {
        /**
         * ------------------------------------------------------------------------------------
         *  NEXTLOOP - modified to allow all file types
         *  This can be done by setting an empty array
         *  [EXAMPLE]
         *  $this->data['allowed_extensions'] = array(); //allow file types/extensions
         *  $this->fileupload->allowedExtensions = $this->data['allowed_extensions'];
         *  
         *  --OR SIMPLY--
         *  $this->fileupload->allowedExtensions = array(); //allows all file types
         * ------------------------------------------------------------------------------------
         */
        if (is_array($allowedExtensions) && empty($allowedExtensions)) {
            return true;
        }

        if (!is_array($allowedExtensions)) {
            return false;
        }

        if (!in_array(strtolower($ext), array_map('strtolower', $allowedExtensions))) {
            return false;
        }
        return true;
    }

    private function setErrorMsg($msg)
    {
        $this->errorMsg = $msg;
    }

    private function fixDir($dir)
    {
        $last = substr($dir, -1);
        if ($last == '/' || $last == '\\') {
            $dir = substr($dir, 0, -1);
        }
        return $dir . DIRECTORY_SEPARATOR;
    }

    // escapeJS and jsMatcher are adapted from the Escaper component of
    // Zend Framework, Copyright (c) 2005-2013, Zend Technologies USA, Inc.
    // https://github.com/zendframework/zf2/tree/master/library/Zend/Escaper
    private function escapeJS($string)
    {
        return preg_replace_callback('/[^a-z0-9,\._]/iSu', $this->jsMatcher, $string);
    }

    private function jsMatcher($matches)
    {
        $chr = $matches[0];
        if (strlen($chr) == 1) {
            return sprintf('\\x%02X', ord($chr));
        }
        if (function_exists('iconv')) {
            $chr = iconv('UTF-16BE', 'UTF-8', $chr);
        } elseif (function_exists('mb_convert_encoding')) {
            $chr = mb_convert_encoding($chr, 'UTF-8', 'UTF-16BE');
        }
        return sprintf('\\u%04s', strtoupper(bin2hex($chr)));
    }

    public function corsResponse($data)
    {
        if (isset($_REQUEST[$this->corsInputName])) {
            $targetOrigin = $this->escapeJS($_REQUEST[$this->corsInputName]);
            $targetOrigin = htmlspecialchars($targetOrigin, ENT_QUOTES, 'UTF-8');
            return "<script>window.parent.postMessage('$data','$targetOrigin');</script>";
        }
        return $data;
    }

    public function handleUpload($uploadDir = null, $allowedExtensions = null)
    {
        if ($this->handler === false) {
            $this->setErrorMsg('Incorrect upload filed name or no file uploaded');
            return false;
        }

        if (!empty($uploadDir)) {
            $this->uploadDir = $uploadDir;
        }
        if (is_array($allowedExtensions)) {
            $this->allowedExtensions = $allowedExtensions;
        }

        $this->uploadDir = $this->fixDir($this->uploadDir);

        if (!empty($this->newFileName)) {
            $this->fileName = $this->newFileName;
            $this->savedFile = $this->uploadDir . $this->newFileName;
        } else {
            $this->savedFile = $this->uploadDir . $this->fileName;
        }

        if ($this->fileSize == 0) {
            $this->setErrorMsg('File is empty');
            return false;
        }

        /** NEXTLOOP - Create directory if it does not exist
         *  Also recurse and create all directory in this
         *  tree if they do not exist
         **/
        if (!is_dir($this->uploadDir) && !is_file($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }

        if (!is_writable($this->uploadDir)) {
            $this->setErrorMsg('Upload directory is not writable');
            return false;
        }
        if ($this->fileSize > $this->sizeLimit) {
            $this->setErrorMsg('File size exceeds limit');
            return false;
        }
        if (!empty($this->allowedExtensions)) {
            if (!$this->checkExtension($this->fileExtension, $this->allowedExtensions)) {
                $this->setErrorMsg('Invalid file type');
                return false;
            }
        }
        if (!$this->handler->Save($this->savedFile)) {
            $this->setErrorMsg('File could not be saved');
            return false;
        }

        return true;
    }

}

/* End of file Fileupload.php */
/* Location: ./application/libraries/Fileupload.php */
