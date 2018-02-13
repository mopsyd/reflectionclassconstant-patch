<?php

/*
 * The MIT License
 *
 * @author Brian Dayhoff <mopsyd@me.com>
 * @copyright (c) 2018, Brian Dayhoff <mopsyd@me.com> all rights reserved.
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * This is a backwards compatible patch of the PHP 7.1 ReflectionClassConstant
 * class, which emulates the high level functionality of it for versions of
 * PHP prior to PHP 7.1. The doc-block parser for this is much slower than the
 * native version, but it works.
 */
if ( !class_exists( '\\ReflectionClassConstant' ) )
{

    class ReflectionClassConstant
    implements \Reflector
    {

        public $name;
        public $class;

        public function __construct( $class, $name )
        {
            if ( is_object( $class ) )
            {
                $class = get_class( $class );
            }
            if ( !is_string( $class ) )
            {
                throw new \ReflectionException( 'Invalid class parameter.' );
            }
            if ( !is_string( $name ) )
            {
                throw new \ReflectionException( 'Invalid class constant name parameter.' );
            }
            $this->validateClassConstant( $class, $name );
            $this->name = $name;
            $this->class = $class;
        }

        public function __toString()
        {
            return (string) $this->getValue();
        }

        public static function export( $class, $name, $return )
        {
            //This is a bunch of low level C stuff that isn't going to
            //work if it's not integrated into the PHP core for the
            //current version.
            return false;
        }

        public function getDocComment()
        {
            $comment = $this->getDocBlock( $this->class );
            if ( is_null( $comment ) )
            {
                return false;
            }
            return $comment;
        }

        public function getModifiers()
        {
            //If there is no predefined ReflectionClassConstant,
            //then the only possible attributes of a class constant
            //are the default public and static bitwise modifiers.
            //Additional modifiers were introduced in the same version
            //of PHP that introduces ReflectionClassConstant.
            return \ReflectionMethod::IS_PUBLIC | \ReflectionMethod::IS_STATIC;
        }

        public function getDeclaringClass()
        {
            return $this->class;
        }

        public function getName()
        {
            return $this->name;
        }

        public function getValue()
        {
            $const = $this->class . '::' . $this->name;
            return constant( $const );
        }

        public function isPrivate()
        {
            //If there is no predefined ReflectionClassConstant,
            //then visibility for constants is not implemented
            //and this will always be false. Constant privacy
            //was introduced in the same version of PHP that
            //introduces ReflectionClassConstant.
            return false;
        }

        public function isProtected()
        {
            //If there is no predefined ReflectionClassConstant,
            //then visibility for constants is not implemented
            //and this will always be false. Constant privacy
            //was introduced in the same version of PHP that
            //introduces ReflectionClassConstant.
            return false;
        }

        public function isPublic()
        {
            //If there is no predefined ReflectionClassConstant,
            //then visibility for constants is not implemented
            //and this will always be true. Constant privacy was
            //introduced in the same version of PHP that
            //introduces ReflectionClassConstant.
            return true;
        }

        private function validateClassConstant( $class, $name )
        {
            if ( !class_exists( $class ) )
            {
                throw new \ReflectionException( sprintf( 'Class %s does not exist.',
                    $class ) );
            }
            $const = $class . '::' . $name;
            if ( !defined( $const ) )
            {
                throw new \ReflectionException( sprintf( 'Class constant %s does not exist in %s.',
                    $const, $class ) );
            }
        }

        private function getDocBlock( $class )
        {
            $reflector = new \ReflectionClass( $class );
            if ( strpos( file_get_contents( $reflector->getFileName() ),
                    'const ' . $this->name ) !== false )
            {
                $content = file_get_contents( $reflector->getFileName() );
                $tokens = token_get_all( $content );
                $comments = $this->docBlockParseTokens( $tokens );
                return $comments[$this->name];
            } else
            {
                //Check the chain of class inheritance
                //for the literal constant definition.
                $interfaces = class_implements( $class );
                while ( $class = get_parent_class( $class ) )
                {
                    $reflector = new \ReflectionClass( $class );
                    $content = file_get_contents( $reflector->getFileName() );
                    if ( strpos( $content, 'const ' . $this->name ) === false )
                    {
                        continue;
                    }
                    $tokens = token_get_all( $content );
                    $comments = $this->docBlockParseTokens( $tokens );
                    return $comments[$this->name];
                }
                //We'll have to check the interfaces if
                //it didn't exist in any of the classes.
                while ( !empty( $interfaces ) )
                {
                    $interface = array_shift( $interfaces );
                    $reflector = new \ReflectionClass( $interface );
                    $content = file_get_contents( $reflector->getFileName() );
                    if ( strpos( $content, 'const ' . $this->name ) === false )
                    {
                        continue;
                    }
                    $tokens = token_get_all( $content );
                    $comments = $this->docBlockParseTokens( $tokens );
                    return $comments[$this->name];
                }
            }
            //Nothing was found.
            return null;
        }

        private function docBlockParseTokens( $tokens )
        {
            $doc = null;
            $isConst = false;
            $comments = array();
            foreach ( $tokens as
                $token )
            {
                if ( count( $token ) <= 1 )
                {
                    continue;
                }

                list($tokenType, $tokenValue) = $token;

                switch ( $tokenType )
                {
                    // ignored tokens
                    case T_WHITESPACE:
                    case T_COMMENT:
                        break;

                    case T_DOC_COMMENT:
                        $doc = $tokenValue;
                        break;

                    case T_CONST:
                        $isConst = true;
                        break;

                    case T_STRING:
                        if ( $isConst )
                        {
                            $comments[$tokenValue] = $doc;
                        }
                        $doc = null;
                        $isConst = false;
                        break;

                    // all other tokens reset the parser
                    default:
                        $doc = null;
                        $isConst = false;
                        break;
                }
            }
            return $comments;
        }

    }

}