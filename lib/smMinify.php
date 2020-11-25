<?php

/**
 * @author Polevik Yurii <yr4ik_07@online.ua>
 */
 
 

namespace yr4ik\smMinify;
 

class smMinify
{
	private $vendor_dir;
	
	
    public function __construct($node_dir=false)
	{
		
		if(!function_exists('exec'))
			throw new \Exception('Function exec required for smMinify');

		
		if(empty($node_dir))
			$this->vendor_dir = __DIR__ . '/vendor';
		else
			$this->vendor_dir = rtrim($node_dir, '/');
				
		if(!is_dir($this->vendor_dir . '/node_modules') || filemtime($this->vendor_dir . '/package.json') > filemtime($this->vendor_dir . '/node_modules') )
		{
			if(is_file($this->vendor_dir . '/node_modules/.lock'))
				die('nodejs was install');
			
			$exec = array();
			$exec[] = 'cd ' . $this->vendor_dir;
			
			$exec[] = 'touch node_modules/.lock';
			
			if(is_dir($this->vendor_dir . '/node_modules'))
				$exec[] = 'rm -rf node_modules/';
			
			$exec[] = 'npm install --no-package-lock 2>&1';
			
			$exec[] = 'rm node_modules/.lock';
			
			exec(implode(' && ', $exec));
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
     * @return  array
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
     * @return  string
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
