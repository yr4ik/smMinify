<?php

/**
 * @author Polevik Yurii <yr4ik_07@online.ua>
 */


namespace yr4ik\smMinify;


class smMinify
{
    private $vendor_dir;

    // По коду выше видно, что в exec_css/exec_js вы обращаетесь к $this->node_modules_dir,
    // поэтому логично объявить её как свойство. Дополняем слэши в начале и конце,
    // чтобы объединение путей было проще:
    private $node_modules_dir = DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR;

    const INSTALL_LOCK_FILE = 'install.lock';
    const PACKAGE_JSON = 'package.json';
    const SMM_EXEC_SCRIPT = 'vendor' . DIRECTORY_SEPARATOR . 'smm_exec.js';

    /**
     * Флаг: true, если ОС — Windows
     */
    private $isWindows = false;

    /**
     * Конструктор.
     * Если $vendor_dir не передан, будет создана и использована папка vendor рядом с этим скриптом.
     * Проверяет/устанавливает node_modules, если нужно.
     *
     * @param string|false $vendor_dir
     */
    public function __construct($vendor_dir = false)
    {
        // Проверяем, доступна ли функция exec (может быть отключена в php.ini)
        if (!function_exists('exec')) {
            throw new \Exception('Function exec required for smMinify');
        }

        // Определяем, Windows ли это
        $this->isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

        // Определяем путь к vendor
        if ($vendor_dir) {
            // Убираем возможные завершающие / или \
            $vendor_dir = rtrim($vendor_dir, '/\\');
            $this->vendor_dir = $vendor_dir;
        } else {
            $this->vendor_dir = __DIR__ . DIRECTORY_SEPARATOR . 'vendor';
        }

        // Создаём папку, если её нет
        if (!is_dir($this->vendor_dir)) {
            mkdir($this->vendor_dir, 0755, true);
        }

        // Формируем полный путь к lock-файлу
        $install_lock_path = $this->vendor_dir . DIRECTORY_SEPARATOR . self::INSTALL_LOCK_FILE;
        // Если существует install.lock, останавливаемся
        if (is_file($install_lock_path)) {
            die('nodejs installing');
        }

        // Подготавливаем пути
        $node_modules_path = $this->vendor_dir . $this->node_modules_dir;
        $package_json_src = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . self::PACKAGE_JSON;
        $package_json_dst = $this->vendor_dir . DIRECTORY_SEPARATOR . self::PACKAGE_JSON;

        // Если папка node_modules не существует ИЛИ package.json новее node_modules — переустанавливаем
        if (
            !is_dir($node_modules_path)
            || (is_file($package_json_src) && filemtime($package_json_src) > filemtime($node_modules_path))
        ) {
            $exec = [];

            // Переходим в папку vendor
            $exec[] = 'cd "' . $this->vendor_dir . '"';

            // Удаляем node_modules, если есть
            if (is_dir($node_modules_path)) {
                $exec[] = $this->isWindows
                    ? 'rmdir /S /Q "' . $node_modules_path . '"'
                    : 'rm -rf "' . $node_modules_path . '"';
            }

            // Создаём install.lock
            $exec[] = $this->isWindows
                ? 'echo. > "' . $install_lock_path . '"'
                : 'touch "' . $install_lock_path . '"';

            // Создаём папку node_modules
            $exec[] = $this->isWindows
                ? 'if not exist "' . $node_modules_path . '" mkdir "' . $node_modules_path . '"'
                : 'mkdir -p "' . $node_modules_path . '"';

            // Копируем package.json
            if ($this->isWindows) {
                // Заменяем / на \ для copy
                $win_src = str_replace('/', '\\', $package_json_src);
                $win_dst = str_replace('/', '\\', $package_json_dst);
                $exec[] = 'copy "' . $win_src . '" "' . $win_dst . '"';
            } else {
                $exec[] = 'cp "' . $package_json_src . '" "' . $package_json_dst . '"';
            }

            // Устанавливаем зависимости npm (создаётся install.log)
            $exec[] = $this->isWindows
                ? 'npm install --no-package-lock > install.log 2>&1'
                : 'npm install --no-package-lock 2>&1 | tee install.log';

            // Удаляем install.lock
            $exec[] = $this->isWindows
                ? 'del "' . $install_lock_path . '"'
                : 'rm "' . $install_lock_path . '"';

            // Выполняем команды
            exec(implode(' && ', $exec));

            // Останавливаем выполнение скрипта
            die('nodejs installed');
        }
    }

    /**
     * Сжатие CSS
     *
     * @param mixed $css
     * @return array
     * @throws \Exception
     */
    public function exec_css($css, $data = [])
    {
        // Проверяем, есть ли папка node_modules
        if (!is_dir($this->vendor_dir . $this->node_modules_dir)) {
            throw new \Exception('Not install node modules');
        }

        $data['css'] = $css;
        $output = $this->exec_proc($data);

        if (isset($output['status']) && $output['status'] === 'error') {
            throw new \Exception($output['error']);
        }

        return [$output['code'], $output['map'], $output['src_files']];
    }

    /**
     * Сжатие JS
     *
     * @param mixed $js
     * @return string
     * @throws \Exception
     */
    public function exec_js($js, $data = [])
    {
        if (!is_dir($this->vendor_dir . $this->node_modules_dir)) {
            throw new \Exception('Not install node modules');
        }

        $data['js'] = (array)$js;
        $output = $this->exec_proc($data);

        if (isset($output['status']) && $output['status'] === 'error') {
            throw new \Exception($output['error']);
        }

        return $output['code'];
    }

    /**
     * Запуск node/bin/node (или node.exe) со скриптом smm_exec.js
     * и передача данных через STDIN.
     */
    private function exec_proc($data)
    {
        // Определяем путь к Node.js, с учётом ОС:
        if ($this->isWindows) {
            // node.exe лежит в: node_modules\node\bin\node.exe
            $nodePath = $this->vendor_dir . $this->node_modules_dir . 'node\\bin\\node.exe';
            // Заменяем / на \ в пути к скрипту
            $smmExecJs = str_replace('/', '\\', __DIR__ . '/vendor/smm_exec.js');

        } else {
            // На Linux/macOS лежит в node_modules/node/bin/node
            $nodePath = $this->vendor_dir . $this->node_modules_dir . 'node/bin/node';
            $smmExecJs = __DIR__ . '/vendor/smm_exec.js';
        }

        // Запускаем процесс
        $nodejs = proc_open($nodePath . ' ' . $smmExecJs, [
            ['pipe', 'r'], // STDIN
            ['pipe', 'w'], // STDOUT
        ], $pipes);

        if (!is_resource($nodejs)) {
            throw new \Exception('Could not reach node runtime');
        }

        // Передаём в скрипт путь к node_modules и JSON-данные
        $data['node_modules_dir'] = $this->vendor_dir . $this->node_modules_dir;
        $this->fwrite_stream($pipes[0], json_encode($data));
        fclose($pipes[0]);

        // Читаем результат
        $output_str = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($nodejs);

        // Парсим JSON-ответ
        $output = json_decode($output_str, true);

        // Если не смогли распарсить, но строка не пустая, кидаем исключение
        if (!$output && $output_str) {
            throw new \Exception($output_str);
        }

        return $output;
    }

    /**
     * Запись в поток (STDIN) с учётом больших данных (буфер).
     *
     * @param resource $fp
     * @param string $string
     * @param int $buflen
     * @return int     Количество записанных байт
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
