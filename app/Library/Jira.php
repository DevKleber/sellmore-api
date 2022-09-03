<?php

namespace App\Library;

use Helpers;

class Jira
{
    public static function newTask($req)
    {
        $data = [
            'key' => env('API_TRELLO_KEY'),
            'token' => env('API_TRELLO_TOKEN'),
            'idList' => '5fa5b2a076723b1f7479eb8e',
            'name' => $req['name'],
            'desc' => $req['desc'],
            'pos' => 'top',
        ];

        $options['url'] = env('API_TRELLO_URL').'/card';
        $options['post'] = true;

        $result = Helpers::useCurl($options, $data);
        if (isset($result->id)) {
            return $result;
        }

        throw new Exception('Erro ao reportar', 1);
    }
}
