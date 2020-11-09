


var data = '';

process.stdin.on('data', function(chunk) {
    data += chunk;
});

process.stdin.on('end', function() {
	
	var result = {};
	result.status = 'done';
	
	try {
		
		data = JSON.parse(data);
		
		if(data.js)
		{
			var UglifyJS = require('uglify-js');
			
			if(!data.options)
				data.options = {
					ie8: true
				};
			
			var res = UglifyJS.minify(data.js, data.options );
			
			if(res.error)
				throw new TypeError('SyntaxError: '+res.error.message+'\n'+'File: ' + res.error.filename + ' [line:'+res.error.line+' col:'+res.error.col+']');
			
			result.code = res.code;
		}
		else if(data.css)
		{
			switch(data.options.preproc)
			{
				case 'scss':
					var sass = require('node-sass');


					
					break;
				case 'less':
				
					break;
			}
			
			var CleanCSS = require('clean-css');

			
		}

	} catch (e) {
		result = {};
		result.status = 'error';
		result.error = e.message;
	}

    process.stdout.write(JSON.stringify(result));
});
