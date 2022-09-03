<?php

class Helpers
{
    public static function count($arrayOrContable)
    {
        if (is_countable($arrayOrContable) || is_array($arrayOrContable) || $arrayOrContable instanceof \Countable) {
            return count($arrayOrContable);
        }

        return null === $arrayOrContable || !isset($arrayOrContable) ? 0 : 1;
    }

    public static function formatCnpjCpf($value)
    {
        $cnpj_cpf = preg_replace('/\\D/', '', $value);

        if (11 === strlen($cnpj_cpf)) {
            return preg_replace('/(\\d{3})(\\d{3})(\\d{3})(\\d{2})/', '$1.$2.$3-$4', $cnpj_cpf);
        }

        return preg_replace('/(\\d{2})(\\d{3})(\\d{3})(\\d{4})(\\d{2})/', '$1.$2.$3/$4-$5', $cnpj_cpf);
    }

    public static function validarExt($ext, $arExt)
    {
        if (in_array($ext, $arExt)) {
            return true;
        }

        return false;
    }

    public static function convertdateBr2DB($date)
    {
        if (empty($date)) {
            return null;
        }
        $arDate = explode('/', $date);
        if (count($arDate) <= 1) {
            return $date;
        }

        return date("{$arDate[2]}-{$arDate[1]}-{$arDate[0]}");

        return date('Y-m-d', strtotime(str_replace('-', '/', $date)));
    }

    public static function convertDateWithoutSeparatorToDatabase($date)
    {
        $exOpcao1 = explode('-', $date);
        $exOpcao2 = explode('/', $date);
        if (count($exOpcao1) > 1) {
            return $date;
        }
        if (count($exOpcao2) > 1) {
            return date('Y-m-d', strtotime(str_replace('/', '-', $date)));
        }

        return substr($date, 4, 4) . '-' . substr($date, 2, 2) . '-' . substr($date, 0, 2);
    }

    public static function convertHourWithoutSeparatorToDatabase($date)
    {
        $exOpcao1 = explode(':', $date);
        if (count($exOpcao1) > 1) {
            return $date;
        }
        $hour = substr($date, 0, 2);
        $minute = substr($date, 2, 2);
        $seconds = substr($date, 4, 2);

        return $hour . ':' . $minute . ':' . $seconds;
    }

    public static function removerCaracteresPhone($conteudo)
    {
        return str_replace(['(', ')', '-'], '', $conteudo);
    }

    public static function removerCaracteresEspeciaisEspacos($conteudo)
    {
        return str_replace(['(', ')', '[', ']', '{', '}', '-', ',', '.', '/', '\\', ';', ':', '?', '!', ' ', '°', 'º', "'"], '', $conteudo);
    }

    public static function convertdateBr2DBTs($date)
    {
        return date('Y-m-d H:i:s', strtotime(str_replace('-', '/', $date)));
    }

    public static function removerVazio($controler, $request)
    {
        foreach ($request as $key => $value) {
            if (!empty($value)) {
                $tipo = substr($key, 0, 2);
                $controler->{$key} = $value;
                if ('dt' == $tipo) {
                    $controler->{$key} = Helpers::convertdateBr2DB($value);
                }
            }
        }

        return $controler;
    }

    public static function validarCpf($cpf)
    {
        $cpf = self::removerCaracteresEspeciaisEspacos($cpf);
        $cpf = trim($cpf);
        if (empty($cpf) || 11 != strlen($cpf)) {
            return false;
        }
        $Numero[1] = intval(substr($cpf, 1 - 1, 1));
        $Numero[2] = intval(substr($cpf, 2 - 1, 1));
        $Numero[3] = intval(substr($cpf, 3 - 1, 1));
        $Numero[4] = intval(substr($cpf, 4 - 1, 1));
        $Numero[5] = intval(substr($cpf, 5 - 1, 1));
        $Numero[6] = intval(substr($cpf, 6 - 1, 1));
        $Numero[7] = intval(substr($cpf, 7 - 1, 1));
        $Numero[8] = intval(substr($cpf, 8 - 1, 1));
        $Numero[9] = intval(substr($cpf, 9 - 1, 1));
        $Numero[10] = intval(substr($cpf, 10 - 1, 1));
        $Numero[11] = intval(substr($cpf, 11 - 1, 1));
        $soma = 10 * $Numero[1] + 9 * $Numero[2] + 8 * $Numero[3] + 7 * $Numero[4] + 6 * $Numero[5] + 5 * $Numero[6] + 4 * $Numero[7] + 3 * $Numero[8] + 2 * $Numero[9];
        $soma = $soma - (11 * (intval($soma / 11)));
        if (0 == $soma || 1 == $soma) {
            $resultado1 = 0;
        } else {
            $resultado1 = 11 - $soma;
            if ($resultado1 == $Numero[10]) {
                $soma = $Numero[1] * 11 + $Numero[2] * 10 + $Numero[3] * 9 + $Numero[4] * 8 + $Numero[5] * 7 + $Numero[6] * 6 + $Numero[7] * 5 + $Numero[8] * 4 + $Numero[9] * 3 + $Numero[10] * 2;
                $soma = $soma - (11 * (intval($soma / 11)));
                if (0 == $soma || 1 == $soma) {
                    $resultado2 = 0;
                } else {
                    $resultado2 = 11 - $soma;
                }
                if ($resultado2 == $Numero[11]) {
                    return true;
                }

                return false;
            }

            return false;
        }

        return true;
    }

    public static function processar($controler, $request)
    {
        foreach ($request as $key => $value) {
            $tipo = substr($key, 0, 2);
            $controler->{$key} = (!empty($value)) ? $value : null;
            if ('dt' == $tipo) {
                $controler->{$key} = Helpers::convertdateBr2DB($value);
            }
        }

        return $controler;
    }

    public static function processarColunas($colunas, $request)
    {
        $ar = [];
        foreach ($request as $key => $value) {
            if (in_array($key, $colunas)) {
                $tipo = substr($key, 0, 2);
                $ar[$key] = (!empty($value)) ? $value : null;
                if ('dt' == $tipo) {
                    $ar[$key] = Helpers::convertdateBr2DB($value);
                }
            }
        }

        return $ar;
    }

    public static function processarColunasUpdate($colunas, $request)
    {
        $columns = $colunas->getFillable();

        foreach ($request as $key => $value) {
            if (in_array($key, $columns)) {
                $colunas->{$key} = $value;
            } else {
                if ('fileimg' === $key && !is_null($value) && in_array('img', $columns)) {
                    $colunas->img = $value;
                }
                if ('fileimg_logo' === $key && !is_null($value) && in_array('img_logo', $columns)) {
                    $colunas->img_logo = $value;
                }
            }
        }

        return $colunas;
    }

    public static function saveFileGeneric($file, $folder)
    {
        $doc = $file;
        //Recupera o nome original do arquivo
        $filename = $doc->getClientOriginalName();

        //Recupera a extensão do arquivo
        $extension = $doc->getClientOriginalExtension();
        //Definindo um nome unico para o arquivo
        $name = date('His_Ymd') . '_' . str_replace(' ', '', $filename);

        //Diretório onde será salvo os arquivos
        $destinationPath = 'img/' . $folder;
        //Move o arquivo para a pasta indicada
        if ($doc->move($destinationPath, $name)) {
            return ['file' => $name];
        }

        return false;
    }

    public static function salveFile($request, $folder)
    {
        if ($request->hasFile('fileimg')) {
            $doc = $request->file('fileimg');

            //Recupera o nome original do arquivo
            $filename = $doc->getClientOriginalName();

            //Recupera a extensão do arquivo
            $extension = $doc->getClientOriginalExtension();

            //Definindo um nome unico para o arquivo
            $name = date('His_Ymd') . '_' . str_replace(' ', '', $filename);

            //Diretório onde será salvo os arquivos
            $destinationPath = 'img/' . $folder;
            //Move o arquivo para a pasta indicada
            if ($doc->move($destinationPath, $name)) {
                return ['file' => $name];
            }
        }

        return false;
    }

    public static function getOSClient()
    {
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $os_platform = 'Unknown OS Platform';
        $os_array = [
            '/windows/i' => 'Windows',
            '/linux/i' => 'Linux',
            '/iphone/i' => 'iPhone',
            '/ipod/i' => 'iPod',
            '/ipad/i' => 'iPad',
            '/android/i' => 'Android',
        ];

        foreach ($os_array as $regex => $value) {
            if (preg_match($regex, $user_agent)) {
                $os_platform = $value;
            }
        }

        return $os_platform;
    }

    public static function getUserIpAddr()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }

    public static function get_browser_name()
    {
        // Make case insensitive.
        $t = strtolower($_SERVER['HTTP_USER_AGENT']);

        $t = ' ' . $t;

        // Humans / Regular Users
        if (strpos($t, 'opera') || strpos($t, 'opr/')) {
            return 'Opera';
        }
        if (strpos($t, 'edge')) {
            return 'Edge';
        }
        if (strpos($t, 'chrome')) {
            return 'Chrome';
        }
        if (strpos($t, 'safari')) {
            return 'Safari';
        }
        if (strpos($t, 'firefox')) {
            return 'Firefox';
        }
        if (strpos($t, 'msie') || strpos($t, 'trident/7')) {
            return 'Internet Explorer';
        }
        // Search Engines
        if (strpos($t, 'google')) {
            return '[Bot] Googlebot';
        }
        if (strpos($t, 'bing')) {
            return '[Bot] Bingbot';
        }
        if (strpos($t, 'slurp')) {
            return '[Bot] Yahoo! Slurp';
        }
        if (strpos($t, 'duckduckgo')) {
            return '[Bot] DuckDuckBot';
        }
        if (strpos($t, 'baidu')) {
            return '[Bot] Baidu';
        }
        if (strpos($t, 'yandex')) {
            return '[Bot] Yandex';
        }
        if (strpos($t, 'sogou')) {
            return '[Bot] Sogou';
        }
        if (strpos($t, 'exabot')) {
            return '[Bot] Exabot';
        }
        if (strpos($t, 'msn')) {
            return '[Bot] MSN';
        }
        // Common Tools and Bots
        if (strpos($t, 'mj12bot')) {
            return '[Bot] Majestic';
        }
        if (strpos($t, 'ahrefs')) {
            return '[Bot] Ahrefs';
        }
        if (strpos($t, 'semrush')) {
            return '[Bot] SEMRush';
        }
        if (strpos($t, 'rogerbot') || strpos($t, 'dotbot')) {
            return '[Bot] Moz or OpenSiteExplorer';
        }
        if (strpos($t, 'frog') || strpos($t, 'screaming')) {
            return '[Bot] Screaming Frog';
        }
        // Miscellaneous
        if (strpos($t, 'facebook')) {
            return '[Bot] Facebook';
        }
        if (strpos($t, 'pinterest')) {
            return '[Bot] Pinterest';
        }
        // Check for strings commonly used in bot user agents
        if (
            strpos($t, 'crawler') || strpos($t, 'api') ||
            strpos($t, 'spider') || strpos($t, 'http') ||
            strpos($t, 'bot') || strpos($t, 'archive') ||
            strpos($t, 'info') || strpos($t, 'data')
        ) {
            return '[Bot] Other';
        }

        return 'Other (Unknown)';
    }

    public static function getFirstNameFromUrlClient($request)
    {
        $path = parse_url($request->header('Referer'), PHP_URL_PATH);
        if (!$path) {
            return null;
        }
        $arFolder = explode('/', $path);
        $folder = current(array_filter($arFolder, fn ($value) => !is_null($value) && '' !== $value));

        return $folder ?? null;
    }

    public static function remove_emoji($string)
    {
        // Match Emoticons
        $regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clear_string = preg_replace($regex_emoticons, '', $string);

        // Match Miscellaneous Symbols and Pictographs
        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clear_string = preg_replace($regex_symbols, '', $clear_string);

        // Match Transport And Map Symbols
        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clear_string = preg_replace($regex_transport, '', $clear_string);

        // Match Miscellaneous Symbols
        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
        $clear_string = preg_replace($regex_misc, '', $clear_string);

        // Match Symbols
        $regex_another = '/[\x{10000}-\x{10FFFF}]/u';
        $clear_string = preg_replace($regex_another, '', $clear_string);

        // Match Dingbats
        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';

        return preg_replace($regex_dingbats, '', $clear_string);
    }

    public static function numeroNonoDigito($numero)
    {
        if (11 == strlen($numero)) { //Se numero tem 13 digitos 64 9 99967545
            return $numero;
        }

        if (10 == strlen($numero)) { //se numero tem 12 64 99954785 falta o 9
            $ddd = substr($numero, 0, 2); //Pega ddd e numero e o pais
            $num = substr($numero, 2);

            if (2 == $num[0] || 3 == $num[0]) { //Verifica se é celular, se nao for retorno o proprio numero
                return $numero;
            }

            if (8 == strlen($num)) { //se for celular, so tem 8 numeros, adiciona o 9
                $num = "9{$num}";
            }

            return "{$ddd}{$num}";
        }
        return false;
    }

    public static function useCurl($options, $data = null)
    {
        $username = isset($options['username']) ? $options['username'] : '';
        $password = isset($options['password']) ? $options['password'] : '';
        $options['token'] = isset($options['token']) ? $options['token'] : '';

        $ch = curl_init();

        $headers = ['Accept:application/json', 'Content-Type:application/json', $options['token']];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ('delete' === $options['post']) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            $options['post'] = true;
        }
        curl_setopt($ch, CURLOPT_POST, $options['post']);
        if (null != $data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_URL, $options['url']);
        if ('' != $username) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
        }

        $result = json_decode(curl_exec($ch));
        $ch_error = curl_error($ch);
        curl_close($ch);
        if ($ch_error) {
            return ['error' => $ch_error];
        }

        return $result;
    }
}
