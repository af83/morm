<?php
// vim: ai ts=4 sts=4 et sw=4
// kate: indent-mode cstyle; replace-tabs on; tab-width 4; show-tabs off;
// -*- Mode: PHP; tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*-
/**
 *  Copyright (c) 2008, AF83
 *  All rights reserved.
 *
 *  Redistribution and use in source and binary forms, with or without modification,
 *  are permitted provided that the following conditions are met: 
 *
 *  1° Redistributions of source code must retain the above copyright notice,
 *  this list of conditions and the following disclaimer. 
 *
 *  2° Redistributions in binary form must reproduce the above copyright notice,
 *  this list of conditions and the following disclaimer in the documentation
 *  and/or other materials provided with the distribution. 
 *
 *  3° Neither the name of AF83 nor the names of its contributors may be used
 *  to endorse or promote products derived from this software without specific
 *  prior written permission. 
 *  
 *  THIS SOFTWARE IS PROVIDED BY THE COMPANY AF83 AND CONTRIBUTORS "AS IS"
 *  AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,
 *  THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
 *  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR 
 *  CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,
 *  EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 *  PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 *  PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY
 *  OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 *  NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE,
 *  EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE. 
 *
 * PHP version 5
 *
 * @category  AF83
 * @package exception
*/

class MormSqlException extends Exception
{
    public function __construct($sql_error)
    {
        parent::__construct($sql_error);
    }
    
    public static function getByErrno($sql_errno,$sql_error) {
        switch($sql_errno) {
            
            case 1062:
                $exception_class = 'MormDuplicateEntryException';
            break;
            default:
                $exception_class = 'MormSqlException';
            break;
            }
        
        return new $exception_class($sql_error);
    }
}