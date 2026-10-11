<?php
//Idempotent on purpose: several consumers may include this file in one process (e.g. every test
//file does). The declaration must stay INSIDE the guard — an unconditional top-level class is
//early-bound at compile time, so a guard like `if(class_exists('Autoloader')) return;` would see
//the very class it is about to declare and skip the registration below.
if(!class_exists('Autoloader',false)){
	class Autoloader{
		public static function register(){
			spl_autoload_register(function ($class){
				$file=__DIR__.'/'.str_replace('\\',DIRECTORY_SEPARATOR,$class).'.php';
				if(file_exists($file)){
					require $file;
					return true;
				}
				return false;
			});
		}
	}
	Autoloader::register();
}