<?php
/**
 * http://github.com/mcfedr/file-uploader
 * 
 * Multiple file upload component with progress-bar, drag-and-drop. 
 * Parts © 2010 Andrew Valums ( andrew(at)valums.com ) 
 * Parts © 2011 Fred Cox ( mcfedr(at)gmail.com ) 
 * 
 * Licensed under GNU GPL 3 or later
 */  

/**
 * Common interface for uploaded files
 */
interface qqUploadedFile {
	public function save($path);
	public function getName();
	public function getSize();
}

/**
 * Handle file uploads via XMLHttpRequest
 */
class qqUploadedFileXhr implements qqUploadedFile {

	/**
	 * Save the file to the specified path
	 * @return boolean TRUE on success
	 */
	public function save($path) {
		$input = fopen("php://input", "r");
		$temp = tmpfile();
		$realSize = stream_copy_to_stream($input, $temp);
		fclose($input);

		if ($realSize != $this->getSize()) {
			return false;
		}

		$target = fopen($path, "w");
		fseek($temp, 0, SEEK_SET);
		stream_copy_to_stream($temp, $target);
		fclose($target);

		return true;
	}

	public function getName() {
		return $_GET['qqfile'];
	}

	public function getSize() {
		if (isset($_SERVER["CONTENT_LENGTH"])) {
			return (int) $_SERVER["CONTENT_LENGTH"];
		}
		else {
			throw new Exception('Getting content length is not supported.');
		}
	}

}

/**
 * Handle file uploads via regular form post (uses the $_FILES array)
 */
class qqUploadedFileForm implements qqUploadedFile {

	/**
	 * Save the file to the specified path
	 * @return boolean TRUE on success
	 */
	public function save($path) {
		if (!move_uploaded_file($_FILES['qqfile']['tmp_name'], $path)) {
			return false;
		}
		return true;
	}

	public function getName() {
		return $_FILES['qqfile']['name'];
	}

	public function getSize() {
		return $_FILES['qqfile']['size'];
	}

}

/**
 * Handle uploads from the js qqfileuploader
 * 
 * use handleUpload() for basic uplaod support or access $file for more intergration
 */
class qqFileUploader {

	/**
	 * The file object that is used to access the uplaoded file
	 * @var qqUploadedFile 
	 */
	public $file;

	/**
	 * Create a new Uploader
	 */
	public function __construct() {
		if (isset($_GET['qqfile'])) {
			$this->file = new qqUploadedFileXhr();
		}
		elseif (isset($_FILES['qqfile'])) {
			$this->file = new qqUploadedFileForm();
		}
		else {
			$this->file = false;
		}
	}

	/**
	 * Use to check the php settings are ok for accepting files of $sizeLimit
	 * @param int $sizeLimit bytes
	 * @return bool 
	 */
	public static function checkServerSettings($sizeLimit) {
		$postSize = self::toBytes(ini_get('post_max_size'));
		$uploadSize = self::toBytes(ini_get('upload_max_filesize'));

		return!($postSize < $this->sizeLimit || $uploadSize < $this->sizeLimit);
	}

	private static function toBytes($str) {
		$val = trim($str);
		$last = strtolower($str[strlen($str) - 1]);
		switch ($last) {
			case 'g': $val *= 1024;
			case 'm': $val *= 1024;
			case 'k': $val *= 1024;
		}
		return $val;
	}

	/**
	 * Returns array('success'=>true) or array('error'=>'error message')
	 * 
	 * @param string $uploadDirectory
	 * @param bool $replaceOldFile
	 * @param array $allowedExtensions
	 * @param int $sizeLimit
	 * @return array 
	 */
	public function handleUpload($uploadDirectory, $replaceOldFile = FALSE, array $allowedExtensions = array(), $sizeLimit = 10485760) {
		$allowedExtensions = array_map("strtolower", $allowedExtensions);
		
		if(!self::checkServerSettings($sizeLimit)) {
			return array('error' => "Check server settings to allow uploads up to $sizeLimit");
		}
		
		if (!is_writable($uploadDirectory)) {
			return array('error' => "Server error. Upload directory isn't writable.");
		}

		if (!$this->file) {
			return array('error' => 'No files were uploaded.');
		}

		$size = $this->file->getSize();

		if ($size == 0) {
			return array('error' => 'File is empty');
		}

		if ($size > $sizeLimit) {
			return array('error' => 'File is too large');
		}

		$pathinfo = pathinfo($this->file->getName());
		$filename = $pathinfo['filename'];
		//$filename = md5(uniqid());
		$ext = $pathinfo['extension'];

		if ($allowedExtensions && !in_array(strtolower($ext), $allowedExtensions)) {
			$these = implode(', ', $allowedExtensions);
			return array('error' => 'File has an invalid extension, it should be one of ' . $these . '.');
		}

		if (!$replaceOldFile) {
			/// don't overwrite previous files that were uploaded
			while (file_exists($uploadDirectory . $filename . '.' . $ext)) {
				$filename .= rand(10, 99);
			}
		}

		if ($this->file->save($uploadDirectory . $filename . '.' . $ext)) {
			return array('success' => true);
		}
		else {
			return array('error' => 'Could not save uploaded file.' .
				'The upload was cancelled, or server error encountered');
		}
	}
	
	/**
	 * Use when returning json to fileuploader to encode chars to be handled with iframe correctly
	 * @param string $json
	 * @return string 
	 */
	public static function encodeJson($json) {
		return htmlspecialchars($json, ENT_NOQUOTES);
	}

}

?>