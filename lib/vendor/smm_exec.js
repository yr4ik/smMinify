


var data = '';

process.stdin.on('data', function(chunk) {
    data += chunk;
});

process.stdin.on('end', function() {
	
	var result = {
		status: 'done'
	};

	try {
		
		data = JSON.parse(data);
		
		function _req(module){
			return require(data.node_modules_dir + module);
		}
		
		
		if(data.js)
		{
			var UglifyJS = _req('uglify-js');
			
			if(typeof data.uglify == 'undefined')
				data.uglify = {
					output: {
						ascii_only: true,
						max_line_len: 600
					},
					ie8: true
				};
			
			var res = UglifyJS.minify(data.js, data.uglify);
			
			if(res.error)
				throw new TypeError('SyntaxError: '+res.error.message+'\n in file: ' + res.error.filename + ' [line:'+res.error.line+' col:'+res.error.col+']');

			result.code = res.code;
			
			process.stdout.write(JSON.stringify(result));
		}
		else if(data.css)
		{

			result.src_files = [];
			result.map = false;
	
			var 
				plugins = [],
				postcss_options = {};
				
			switch(data.preproc)
			{
				case 'scss':

					var 
						sass = _req('node-sass'),
						sassopt = {
							data: data.css,
							includePaths: [data.basepath],
							outFile: data.output,
							outputStyle: 'expanded'
						};
						
					
					if(data.original_path)
						sassopt.file = data.original_path;

					if(data.source_map)
						sassopt.sourceMap = data.source_map;

					var res = sass.renderSync(sassopt);

					if(res.map)
						result.map = res.map.toString();
					
					result.code = res.css.toString();
					result.src_files = res.stats.includedFiles;

					break;
				case 'less':
				
					break;
				default:
					result.code = data.css;
					break;
			}
			
				
			if(!data.debug)
			{
				if(data.combine_media)
				{
					plugins.push(_req('postcss-sort-media-queries')({ }));
				}
				
				if(data.autoprefixer)
				{
					var aoptions = {};
					if(data.autoprefixer !== true)
						aoptions = {overrideBrowserslist: data.autoprefixer};
					
					plugins.push(_req('autoprefixer')(aoptions));
				}
				
				if(data.minify)
				{
					plugins.push(_req('cssnano'));
				}
				
			}
			else if(result.map)
			{
				postcss_options.map = {
					inline: false,
					annotation: false
				};
			}
			
			//"postcss-sass-unicode": "^0.1",
			// plugins.push(_req('postcss-sass-unicode'));


			// execute postcss
			if(data.type == 'file')
			{
				postcss_options.from = data.original_path;
				postcss_options.to = data.output;
			}
			

			// connect postcss plugins 
			var postcss = _req('postcss')(plugins);
			
			if(!data.debug)
				postcss = postcss.use(_req('postcss-import'));
			
			postcss
				.process(result.code, postcss_options)
				.then(pr => {
					
					result.code = pr.content;
					
					// save src 
					for (let i = 0; i < pr.messages.length; i++) {
						if(pr.messages[i].type == 'dependency')
							result.src_files.push(pr.messages[i].file); 
					}
					result.test = pr.messages;
					process.stdout.write(JSON.stringify(result));
				})
	
		}

	} catch (e) {
		result = {};
		result.status = 'error';
		result.error = e.formatted || e.message;
		process.stdout.write(JSON.stringify(result));
	}

    
});
