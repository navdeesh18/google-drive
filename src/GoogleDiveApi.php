<?php

namespace Nkcx\GoogleDriveApi;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Drive;
use Google_Service_Sheets_ValueRange;
/**
 * 
 */
class GoogleDriveApi
{
	private $drive, $spreadsheet, $client, $access_token, $created, $expires_in, $refresh_token;

	function __construct($google_tokens)
	{
        $this->access_token = $google_tokens['access_token'];
        $this->created = $google_tokens['created'];
        $this->expires_in = $google_tokens['expires_in'];
        $this->refresh_token = $google_tokens['refresh_token'];

		$client = new Google_Client();
        $client->setAuthConfig([
            'web' => config('services.google')
        ]);

        $accessToken = [
            'access_token' => $this->access_token,
            'created' => $this->created,
            'expires_in' => $this->expires_in,
            'refresh_token' => $this->refresh_token
        ];
        $client->setAccessToken($accessToken);
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            }
            $this->access_token = $client->getAccessToken()['access_token'],
            $this->expires_in = $client->getAccessToken()['expires_in'],
            $this->created = $client->getAccessToken()['created'],
        }

        $client->refreshToken($refresh_token);
        $this->client = $client;
	}

    private function setSpreadsheetService() : void
    {
        $this->spreadsheet = new Google_Service_Sheets($client);
    }

    private function setDriveService() : void
    {
        $this->drive = new Google_Service_Drive($client);
    }

    public function UpdateRow($values, $range, $valueInputOption='RAW') : void
    {
        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);
        $params = [
            'valueInputOption' => $valueInputOption
        ];
        $result = $this->spreadsheet->spreadsheets_values->update($this->spreadsheetId, $range, $body, $params);
        // printf("%d cells updated.", $result->getUpdatedCells());
    }

    public function AppendRow($values, $range='A1', $valueInputOption='RAW') : void
    {
        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);
        $params = [
            'valueInputOption' => $valueInputOption
        ];
        $result = $this->spreadsheet->spreadsheets_values->append($this->spreadsheetId, $range, $body, $params);
    }

    public function BatchUpdate($allData, $valueInputOption='RAW') : void
    {
        $data = [];
        foreach ($allData as $key => $value) {
            $data[] =  new Google_Service_Sheets_ValueRange([
                        'range' => $value['range'],
                        'values' => $value['values']
                    ]);
        }
        $body = new Google_Service_Sheets_BatchUpdateValuesRequest([
            'valueInputOption' => $valueInputOption,
            'data' => $data
        ]);
        $result = $this->spreadsheet->spreadsheets_values->batchUpdate($this->spreadsheetId, $body);
    }

    function createFile($file, $parent_id = null){
        $name = gettype($file) === 'object' ? $file->getClientOriginalName() : $file;
        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $name,
            'parent' => $parent_id ? $parent_id : 'root'
        ]);
 
        $content = gettype($file) === 'object' ?  File::get($file) : Storage::get($file);
        $mimeType = gettype($file) === 'object' ? File::mimeType($file) : Storage::mimeType($file);
 
        $file = $this->drive->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);
 
        dd($file->id);
    }

    public function searchSpreadsheet($filename)
    {
        $query = "mimeType='application/vnd.google-apps.spreadsheet' and trashed=false and name = '".$filename."'";

        $optParams = [
            'fields' => 'files(id, name)',
            'q' => $query
        ];
        $result = $this->drive->files->listFiles($optParams)->getFiles();
        if (count($result) > 0) {
            if (count($result) == 1) {
                return [$result[0]->getName(), $result[0]->getId()];
            }
        }
        return count($result);
    }

    public function createSpreadsheet($fileName, $linkid=null, $for=null) : array
    {
        $name = gettype($fileName) === 'object' ? $fileName->getClientOriginalName() : $fileName;
        // $properties = new Google_Service_Sheets_SpreadsheetProperties;
        // $properties->setTitle($fileName);
        if (is_null($this->spreadsheet)) {
            $this->setSpreadsheetService($linkid);
        }
        $oldFile = $this->searchSpreadsheet($fileName);
        $spreadsheetName = $spreadsheetId = null;
        if (is_array($oldFile)) {
            $spreadsheetName = $oldFile[0];
            $spreadsheetId = $oldFile[1];
        } elseif ($oldFile === 0) {
            $newsheet = new Google_Service_Sheets_Spreadsheet([
                'properties' => [
                    'title' => $fileName,
                ]
            ]);
            $file = $this->spreadsheet->spreadsheets->create($newsheet, [
                'fields' => 'spreadsheetId'
            ]);

            $spreadsheetName = $fileName;
            $spreadsheetId = $file->spreadsheetId;
        } else {
            return (['status'=>'failure', 'message'=>'Too many files with same name in google drive']) ;
        }
        
        return (['status'=>'success', 'sheet_id'=>$spreadsheetId]) ;
    }

    function createFolder($folder_name){
        $folder_meta = new Google_Service_Drive_DriveFile(array(
            'name' => $folder_name,
            'mimeType' => 'application/vnd.google-apps.folder'));
        $folder = $this->drive->files->create($folder_meta, array(
            'fields' => 'id'));
        return $folder->id;
    }


	
}