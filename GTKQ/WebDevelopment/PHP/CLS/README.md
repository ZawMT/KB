# PHP Command-Line Scripting

To test PHP by writing command-line scripts, first install PHP locally. 
In Mac, it can be installed by `brew install php`.
Then `php -v` will show the version.

The PHP files in the CLS folder can be run by using PHP command as follows:    
`php filename.php` to run as a script, output in terminal.    
`php -S localhost:8000` to serve the folder, open `http://localhost:8000`, and it will look for index.php.  
To call the file directly, just run like this: `http://localhost:8000/01_version.php`