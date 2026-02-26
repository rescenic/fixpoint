<?php

class GoogleDriveBackup {
    private $client;
    private $service;
    private $credentials_file;
    
    public function __construct($credentials_file = 'gdrive-credentials.json') {
        $this->credentials_file = $credentials_file;
        
        // Check if credentials file exists
        if (!file_exists($this->credentials_file)) {
            throw new Exception('Credentials file not found: ' . $this->credentials_file);
        }
        
        // Check if Google API Client is installed
        if (!class_exists('Google_Client')) {
            throw new Exception('Google API Client not installed. Run: composer require google/apiclient');
        }
        
        $this->initClient();
    }
    
    /**
     * Initialize Google Client
     */
    private function initClient() {
        try {
            $this->client = new Google_Client();
            $this->client->setAuthConfig($this->credentials_file);
            $this->client->addScope(Google_Service_Drive::DRIVE_FILE);
            $this->client->setSubject(null);
            
            $this->service = new Google_Service_Drive($this->client);
        } catch (Exception $e) {
            throw new Exception('Failed to initialize Google Client: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload file to Google Drive
     * 
     * @param string $filepath Local file path
     * @param string $folder_id Google Drive folder ID
     * @return array Result with file_id and web_link
     */
    public function uploadFile($filepath, $folder_id = null) {
        if (!file_exists($filepath)) {
            return ['success' => false, 'message' => 'File not found: ' . $filepath];
        }
        
        try {
            $filename = basename($filepath);
            $mimeType = 'application/sql';
            
            $fileMetadata = new Google_Service_Drive_DriveFile([
                'name' => $filename,
                'mimeType' => $mimeType
            ]);
            
            // Set parent folder if provided
            if ($folder_id) {
                $fileMetadata->setParents([$folder_id]);
            }
            
            $content = file_get_contents($filepath);
            
            $file = $this->service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id, name, webViewLink, size'
            ]);
            
            return [
                'success' => true,
                'file_id' => $file->id,
                'name' => $file->name,
                'web_link' => $file->webViewLink,
                'size' => $file->size
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete file from Google Drive
     * 
     * @param string $file_id Google Drive file ID
     * @return array Result
     */
    public function deleteFile($file_id) {
        try {
            $this->service->files->delete($file_id);
            return ['success' => true, 'message' => 'File deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * List files in a folder
     * 
     * @param string $folder_id Google Drive folder ID
     * @param int $limit Number of files to list
     * @return array List of files
     */
    public function listFiles($folder_id = null, $limit = 100) {
        try {
            $query = "";
            if ($folder_id) {
                $query = "'{$folder_id}' in parents";
            }
            
            $optParams = [
                'pageSize' => $limit,
                'fields' => 'files(id, name, size, createdTime, webViewLink)',
                'orderBy' => 'createdTime desc'
            ];
            
            if ($query) {
                $optParams['q'] = $query;
            }
            
            $results = $this->service->files->listFiles($optParams);
            
            return [
                'success' => true,
                'files' => $results->getFiles()
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'List failed: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * Simple function to upload backup to Google Drive
 * For use in cron_backup.php
 */
function uploadToGoogleDrive($filepath, $folder_id, $credentials_file = 'gdrive-credentials.json') {
    try {
        $gdrive = new GoogleDriveBackup($credentials_file);
        $result = $gdrive->uploadFile($filepath, $folder_id);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Uploaded to Google Drive',
                'file_id' => $result['file_id'],
                'web_link' => $result['web_link']
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['message']
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Google Drive upload error: ' . $e->getMessage()
        ];
    }
}
?>