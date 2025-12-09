<?php

namespace App\Doctrine\DQL;

use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * RandFunction ::= "RAND" "(" ")"
 * 
 * Database-agnostic random function that works with MySQL, PostgreSQL, and SQLite
 */
class RandFunction extends FunctionNode
{
    public function getSql(SqlWalker $sqlWalker): string
    {
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();
        
        if ($platform instanceof MySQLPlatform) {
            return 'RAND()';
        }
        
        if ($platform instanceof PostgreSQLPlatform) {
            return 'RANDOM()';
        }
        
        if ($platform instanceof SQLitePlatform) {
            return 'RANDOM()';
        }
        
        // Default fallback for other databases
        return 'RAND()';
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }
}
