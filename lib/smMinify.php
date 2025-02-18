<?php

/**
 * @author Polevik Yurii <yr4ik_07@online.ua>
 */
 
 

namespace yr4ik\smMinify;
 

class smMinify
{
    private $vendor_dir;

    const NODE_MODULES_DIR = 'node_modules';
    const INSTALL_LOCK_FILE = 'install.lock';
    const PACKAGE_JSON = 'package.json';
    const SMM_EXEC_SCRIPT = 'vendor' . DIRECTORY_SEPARATOR . 'smm_exec.js';

    /**
     * Constructor
     * Set directory of node modules $this->vendor_dir
     * @param string $vendor_dir
     */
    public function __construct($vendor_dir = false)
    {
        if (!function_exists('exec')) {
            throw new \Exception('Function exec required for smMinify');
        }

        // Установка vendor_dir
        $this->vendor_dir = empty($vendor_dir) 
            ? realpath(__DIR__) . DIRECTORY_SEPARATOR . 'vendor'
            : rtrim(realpath($vendor_dir), DIRECTORY_SEPARATOR);

        if (!is_dir($this->vendor_dir)) {
            mkdir($this->vendor_dir, 0755, true);
        }

        $this->vendor_dir .= DIRECTORY_SEPARATOR;

        // Проверка install.lock
        $install_lock_path = $this->vendor_dir . self::INSTALL_LOCK_FILE;
        if (is_file($install_lock_path)) {
            die('nodejs installing');
        }

        // Пути
        $node_modules_path = $this->vendor_dir . self::NODE_MODULES_DIR;
        $package_json_src = realpath(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . self::PACKAGE_JSON;
        $package_json_dst = $this->vendor_dir . self::PACKAGE_JSON;

        if (!is_dir($node_modules_path) || filemtime($package_json_src) > filemtime($node_modules_path)) {
            $exec = [];
            $exec[] = 'cd "' . $this->vendor_dir . '"';

            $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

            // Удаление node_modules
            if (is_dir($node_modules_path)) {
                $exec[] = $isWindows 
                    ? 'rmdir /S /Q "' . $node_modules_path . '"'
                    : 'rm -rf "' . $node_modules_path . '"';
            }

            // Создание install.lock
            $exec[] = $isWindows 
                ? 'echo. > "' . $install_lock_path . '"'
                : 'touch "' . $install_lock_path . '"';

            // Создание папки node_modules
            $exec[] = $isWindows
                ? 'if not exist "' . $node_modules_path . '" mkdir "' . $node_modules_path . '"'
                : 'mkdir -p "' . $node_modules_path . '"';

            // Копирование package.json
            $exec[] = $isWindows
                ? 'copy "' . $package_json_src . '" "' . $package_json_dst . '"'
                : 'cp "' . $package_json_src . '" "' . $package_json_dst . '"';

            // Установка зависимостей npm и логирование
            $exec[] = $isWindows
                ? 'npm install --no-package-lock > install.log 2>&1'
                : 'npm install --no-package-lock 2>&1 | tee install.log';

            // Удаление install.lock
            $exec[] = $isWindows 
                ? 'del "' . $install_lock_path . '"'
                : 'rm "' . $install_lock_path . '"';

            // Выполнение команд
            exec(implode(' && ', $exec));

            die('nodejs installed');
        }
    }

    /**
     * Выполнение сжатия CSS
     */
    public function exec_css($css, $data = [])
    {
        if (!is_dir($this->vendor_dir . self::NODE_MODULES_DIR)) {
            throw new \Exception('Not install node modules');
        }

        $data['css'] = $css;
        return $this->process_output($data);
    }

    /**
     * Выполнение сжатия JavaScript
     */
    public function exec_js($js, $data = [])
    {
        if (!is_dir($this->vendor_dir . self::NODE_MODULES_DIR)) {
            throw new \Exception('Not install node modules');
        }

        $data['js'] = (array) $js;
        return $this->process_output($data)['code'];
    }

    /**
     * Общая функция для выполнения процесса
     */
    private function process_output($data)
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $node_exec = $this->vendor_dir . self::NODE_MODULES_DIR . DIRECTORY_SEPARATOR . 
                     ($isWindows ? 'node.exe' : 'node/bin/node');
        $smm_exec_script = realpath(__DIR__) . DIRECTORY_SEPARATOR . self::SMM_EXEC_SCRIPT;

        $cmd = '"' . $node_exec . '" "' . $smm_exec_script . '"';

        $process = proc_open($cmd, 
            [ ['pipe', 'r'], ['pipe', 'w'] ],
            $pipes
        );

        if (!is_resource($process)) {
            throw new \Exception('Could not reach node runtime');
        }

        $data['node_modules_dir'] = $this->vendor_dir . self::NODE_MODULES_DIR;
        $this->fwrite_stream($pipes[0], json_encode($data));
        fclose($pipes[0]);

        $output_str = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        $output = json_decode($output_str, true);

        if (!$output && $output_str) {
            throw new \Exception($output_str);
        }

        return $output;
    }

    /**
     * Потоковая запись в STDIN
     */
    private function fwrite_stream($fp, $string, $buflen = 4096)
    {
        for ($written = 0, $len = strlen($string); $written < $len; $written += $fwrite) {
            $fwrite = fwrite($fp, substr($string, $written, $buflen));
            if ($fwrite === false) {
                return $written;
            }
        }
        return $written;
    }
}
