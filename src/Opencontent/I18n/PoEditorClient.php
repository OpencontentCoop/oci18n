<?php

namespace Opencontent\I18n;

use GuzzleHttp\Client;

class PoEditorClient
{
    const ENDPOINT = 'https://api.poeditor.com/v2';

    public static $languageMap = [
        'it' => 'ita-IT',
        'de' => 'ger-DE',
        'en' => 'eng-GB',
        'fr' => 'fre-FR',
    ];

    /**
     * @var string
     */
    private $token;

    /**
     * @var Client
     */
    private $client;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->client = new Client();
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    private function request($path, array $data = [])
    {
        $options = [];
        $options['form_params'] = array_merge([
            'api_token' => $this->token,
        ], $data);

        $response = (string)$this->client->request(
            'POST',
            self::ENDPOINT . $path,
            $options
        )->getBody();

        return json_decode($response, true);
    }

    public function getProjects(): array
    {
        return $this->request('/projects/list')['result']['projects'] ?? [];
    }

    public function getProject($id): array
    {
        return $this->request('/projects/view', ['id' => $id])['result']['project'] ?? [];
    }

    public function getExportUrl(string $projectId, string $language, string $type, ?array $tags = null): ?string
    {
        $requestParams = [
            'id' => $projectId,
            'language' => $language,
            'type' => $type,
        ];
        if ($tags){
            $requestParams['tags'] = json_encode($tags);
        }

        return $this->request('/projects/export', $requestParams)['result']['url'] ?? null;
    }

    public function getTerms($projectId, $language = null): array
    {
        $payload = ['id' => $projectId];
        if ($language){
            $payload['language'] = $language;
        }
        return $this->request('/terms/list', $payload)['result']['terms'] ?? [];
    }

    public function addTerms($projectId, $terms): array
    {
        if (!empty($terms)) {
            return $this->request('/terms/add', [
                'id' => $projectId,
                'data' => json_encode($terms),
            ])['result']['terms'] ?? [];
        }
        return [];
    }

    public function deleteTerms($projectId, $terms): array
    {
        if (!empty($terms)) {
            return $this->request('/terms/delete', [
                'id' => $projectId,
                'data' => json_encode($terms),
            ])['result']['terms'] ?? [];
        }
        return [];
    }

    public function addTranslations($projectId, $language, $translations): array
    {
        if (!empty($translations)) {
            return $this->request('/translations/add', [
                'id' => $projectId,
                'language' => $language,
                'data' => json_encode($translations),
            ])['result']['translations'] ?? [];
        }
        return [];
    }

    public function updateTranslations($projectId, $language, $translations): array
    {
        if (!empty($translations)) {
            return $this->request('/translations/update', [
                'id' => $projectId,
                'language' => $language,
                'data' => json_encode($translations),
            ])['result']['translations'] ?? [];
        }
        return [];
    }
}