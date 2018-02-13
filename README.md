This is just a simple patch of the PHP 7.1 ReflectionClassConstant, which emulates the high level functionality of it and produces predictably similar results to the native class.

It is not as performant as the native class, and should not be relied upon heavily for production if you are running lower than PHP 7.1, though it is sufficient for command line usage that does not need to resolve quickly.

This patch file will not collide with the native ReflectionClassConstant if it exists, and only declares the class if it does not already exist. It declares modifiers and visibility in conjunction with the default class constant settings that existed prior to their introduction as configurable in PHP 7.1.