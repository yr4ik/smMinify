<?php

/**
 * @author Polevik Yurii <yr4ik_07@online.ua>
 */
 
 

namespace yr4ik\smMinify;
 

class smMinify
{
	private $vendor_dir;
	
    /**
	 * Constructor
	 * Set directory of node modules $this->vendor_dir
     * @param   string   $node_dir
     */
    public function __construct($node_dir=false)
	{
		
		if(!function_exists('exec'))
			throw new \Exception('Function exec required for smMinify');

		
		if(empty($node_dir)){
			$this->vendor_dir = __DIR__ . '/vendor';
		}else{
			$this->vendor_dir = rtrim($node_dir, '/');
			if(!is_dir($this->vendor_dir))
				mkdir($this->vendor_dir, 0755, true);
		}

		if(is_file($this->vendor_dir . '/node_modules/.lock'))
			die('nodejs installing');
		
		if(!is_dir($this->vendor_dir . '/node_modules') || filemtime( __DIR__ . '/vendor/package.json') > filemtime($this->vendor_dir . '/node_modules') )
		{
			$exec = array();
			$exec[] = 'cd ' . $this->vendor_dir;
			
			// rm old
			if(is_dir($this->vendor_dir . '/node_modules'))
				$exec[] = 'rm -rf node_modules/';

			// create lock
			$exec[] = 'mkdir node_modules/';
			$exec[] = 'touch node_modules/.lock';
			
			// copy package.json
			$exec[] = 'cp "' . __DIR__ . '/vendor/package.json" "package.json"';
			
			// install
			$exec[] = 'npm install --no-package-lock 2>&1';
			
			// unlock
			$exec[] = 'rm node_modules/.lock';

			exec(implode(' && ', $exec));
			
			die('nodejs installed');
		}
	}
	
    /**
     * @param   mixed   $css
     * @throws Exception
     * @return  array
     */
    public function exec_css($css, $data=array())
    {
		if(!is_dir($this->vendor_dir . '/node_modules'))
			throw new \Exception('Not install node modules');
		
		$data['css'] = $css;

		$output = $this->exec_proc($data);

        if ($output['status'] == 'error') {
            throw new \Exception($output['error']);
        }
        
        return array($output['code'], $output['map'], $output['src_files']);
    }
	
	
    
    /**
     * @param   mixed   $js
     * @throws Exception
     * @return  string
     */
    public function exec_js($js, $data=array())
    {
		if(!is_dir($this->vendor_dir . '/node_modules'))
			throw new \Exception('Not install node modules');

		$data['js'] = (array) $js;
		
		$output = $this->exec_proc($data);
		
        if ($output['status'] == 'error') {
            throw new \Exception($output['error']);
        }
        
        return $output['code'];
    }
    
    private function exec_proc($data)
    {
		$cmd = 'node ' . __DIR__ . '/vendor/smm_exec.js';
		
        $nodejs = proc_open($cmd, 
            array(array('pipe', 'r'), array('pipe', 'w')),
            $pipes
        );

        if (!is_resource($nodejs)) {
            throw new \Exception('Could not reach node runtime');
        }
		
		$data['node_modules_dir'] = $this->vendor_dir . '/node_modules/';

        $this->fwrite_stream($pipes[0],
            json_encode($data));
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);

        $output = json_decode($output, true);
        fclose($pipes[1]);
        
        proc_close($nodejs);
	
		return $output;
	}
	
	
    /**
     * @param   object  $fp         php://stdin
     * @param   string  $string
     * @param   int     $buflen
     * @return  written bytes
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
    
};
