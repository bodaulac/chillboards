<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Drive;
use Illuminate\Support\Facades\Log;

class GoogleSheetService
{
    protected $client;
    protected $sheetsService;
    protected $driveService;

    public function __construct()
    {
        // Lazy init in methods or init here if config always present
    }

    protected function initialize()
    {
        if ($this->client) return;

        $keyJson = config('services.google.service_account_json');
        
        // Handle if key is path or raw json string
        if (file_exists($keyJson)) {
            $authConfig = $keyJson;
        } else {
            $authConfig = json_decode($keyJson, true);
        }

        if (!$authConfig) {
             throw new \Exception("Google Service Account JSON invalid or missing.");
        }

        $this->client = new Client();
        $this->client->setAuthConfig($authConfig);
        $this->client->addScope(Sheets::SPREADSHEETS);
        $this->client->addScope(Drive::DRIVE_FILE);
        $this->client->setApplicationName('OMS Laravel');

        $this->sheetsService = new Sheets($this->client);
        $this->driveService = new Drive($this->client);
    }

    /**
     * Create Walmart Template Sheet (Tab)
     */
    public function createWalmartTemplateSheet($title, $headers, $data)
    {
        $this->initialize();

        $masterId = config('services.google.master_sheet_id');
        if (!$masterId) throw new \Exception("Master Sheet ID not configured.");

        // Unique Tab Name
        $cleanTitle = substr(preg_replace('/[:\/?*\[\]\\\]/', '-', $title), 0, 90);
        $tabName = "{$cleanTitle} (" . rand(100, 999) . ")";

        Log::info("Creating Sheet Tab: {$tabName}");

        try {
            // 1. Add Sheet
            $requestBody = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [
                    'addSheet' => [
                        'properties' => [
                            'title' => $tabName,
                            'gridProperties' => ['frozenRowCount' => 1]
                        ]
                    ]
                ]
            ]);

            $response = $this->sheetsService->spreadsheets->batchUpdate($masterId, $requestBody);
            $sheetId = $response->getReplies()[0]->getAddSheet()->getProperties()->getSheetId();

            // 2. Format Header
            $formatRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
                'requests' => [
                    [
                        'repeatCell' => [
                            'range' => [
                                'sheetId' => $sheetId,
                                'startRowIndex' => 0,
                                'endRowIndex' => 1
                            ],
                            'cell' => [
                                'userEnteredFormat' => [
                                    'backgroundColor' => ['red' => 0.17, 'green' => 0.24, 'blue' => 0.31],
                                    'textFormat' => ['foregroundColor' => ['red' => 1, 'green' => 1, 'blue' => 1], 'bold' => true],
                                    'horizontalAlignment' => 'CENTER',
                                    'verticalAlignment' => 'MIDDLE'
                                ]
                            ],
                            'fields' => 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment,verticalAlignment)'
                        ]
                    ],
                    // Resize columns
                    [
                        'autoResizeDimensions' => [
                            'dimensions' => [
                                'sheetId' => $sheetId,
                                'dimension' => 'COLUMNS',
                                'startIndex' => 0,
                                'endIndex' => count($headers)
                            ]
                        ]
                    ]
                ]
            ]);

            $this->sheetsService->spreadsheets->batchUpdate($masterId, $formatRequest);

            // 3. Write Data
            $values = array_merge([$headers], $data);
            $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
            
            $this->sheetsService->spreadsheets_values->update(
                $masterId,
                "'{$tabName}'!A1",
                $body,
                ['valueInputOption' => 'RAW']
            );

            return [
                'spreadsheetId' => $masterId,
                'spreadsheetUrl' => "https://docs.google.com/spreadsheets/d/{$masterId}/edit#gid={$sheetId}",
                'sheetId' => $sheetId,
                'tabName' => $tabName
            ];

        } catch (\Exception $e) {
            Log::error("Google Sheets Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function readSheet($spreadsheetId, $range)
    {
        $this->initialize();
        $response = $this->sheetsService->spreadsheets_values->get($spreadsheetId, $range);
        return $response->getValues() ?? [];
    }
}
