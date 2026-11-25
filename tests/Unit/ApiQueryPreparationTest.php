<?php

declare(strict_types=1);

require_once RESOURCESPACE_BASE_PATH . '/include/api_functions.php';

it('preserves signed API arguments and rejects ambiguous values', function (string $query, string|false $expected): void {
    expect(prepare_api_query($query))->toBe($expected);
})->with([
    'Empty query' => ['', ''],
    'Empty query components retain their separators' => ['&function=get_resource_data&&param1=1&', '&function=get_resource_data&&param1=1&'],
    'Argument order and original encoding are preserved' => ['param2=a+b%20c%2bd%2F&funct%69on=get_resource_data&param1=%26%3D', 'param2=a+b%20c%2bd%2F&funct%69on=get_resource_data&param1=%26%3D'],
    'Empty and valueless arguments are preserved' => ['param1=&param2&param3=0', 'param1=&param2&param3=0'],
    'Transport fields are removed at the start, middle and end' => ['sign=hash&user=test&authmode=userkey&function=get_resource_data&pretty=true&param1=1&ajax=true', 'user=test&function=get_resource_data&param1=1'],
    'Query containing only transport fields becomes empty' => ['ajax=true&sign=hash&pretty=true&authmode=sessionkey', ''],
    'Empty and valueless transport fields are removed' => ['sign=&authmode&pretty=&ajax&param1=1', 'param1=1'],
    'Encoded transport names are recognised' => ['s%69gn=hash&auth%6Dode=userkey&pr%65tty=true&a%6Aax=true&param1=1', 'param1=1'],
    'Removing transport fields preserves empty query components' => ['&sign=hash&&param1=1&ajax=true&', '&&param1=1&'],
    'Transport-like text and equals signs in values are preserved' => ['param1=sign=value%26pretty%3Dtrue%2Bajax%3D1&param2=authmode=userkey', 'param1=sign=value%26pretty%3Dtrue%2Bajax%3D1&param2=authmode=userkey'],
    'Transport names are case sensitive and require an exact match' => ['Sign=hash&AJAX=true&signature=hash&pretty_print=true', 'Sign=hash&AJAX=true&signature=hash&pretty_print=true'],
    'Transport names within ordinary array arguments are preserved' => ['data[sign]=hash&data[authmode]=userkey&data[pretty]=true&data[ajax]=true', 'data[sign]=hash&data[authmode]=userkey&data[pretty]=true&data[ajax]=true'],
    'Distinct associative array entries' => ['function=put_resource_data&data[access]=1&data[archive]=0', 'function=put_resource_data&data[access]=1&data[archive]=0'],
    'Appended array entries' => ['function=toggle_active_state_for_nodes&refs[]=1&refs[]=2', 'function=toggle_active_state_for_nodes&refs[]=1&refs[]=2'],
    'Interleaved nested array entries' => ['data[a][]=1&data[b]=2&data[a][]=3', 'data[a][]=1&data[b]=2&data[a][]=3'],
    'Encoded array brackets and distinct nested keys' => ['data%5Ba%5D%5Bb%5D=1&data%5Ba%5D%5Bc%5D=2', 'data%5Ba%5D%5Bb%5D=1&data%5Ba%5D%5Bc%5D=2'],
    'Appended entry follows the highest explicit numeric index' => ['refs[2]=1&refs[]=2&refs[0]=3', 'refs[2]=1&refs[]=2&refs[0]=3'],
    'Each appended nested array is a distinct entry' => ['data[][name]=first&data[][name]=second', 'data[][name]=first&data[][name]=second'],
    'Dots and spaces within array keys remain distinct' => ['data[a.b]=1&data[a+b]=2&data[a_b]=3', 'data[a.b]=1&data[a+b]=2&data[a_b]=3'],
    'Duplicate scalar values are rejected even when identical' => ['param1=1&param1=1', false],
    'Duplicate scalar names separated by a transport field' => ['param1=1&ajax=true&param1=2', false],
    'Encoded and literal parameter names collide' => ['param1=1&param%31=2', false],
    'Dots in root names normalise to underscores' => ['param.1=1&param_1=2', false],
    'Plus signs in root names normalise to underscores' => ['param+1=1&param_1=2', false],
    'Encoded spaces in root names normalise to underscores' => ['param%201=1&param_1=2', false],
    'Scalar followed by array' => ['data=1&data[a]=2', false],
    'Array followed by scalar' => ['data[a]=1&data=2', false],
    'Duplicate associative array key' => ['data[access]=0&data[access]=1', false],
    'Nested scalar followed by array' => ['data[a]=1&data[a][b]=2', false],
    'Nested array followed by scalar' => ['data[a][b]=1&data[a]=2', false],
    'Explicit index collides with appended entry' => ['refs[]=1&refs[0]=2', false],
    'Appended entry uses the next index after an explicit index' => ['refs[2]=1&refs[]=2&refs[3]=3', false],
    'Encoded and literal array keys collide' => ['data[a]=1&data%5B%61%5D=2', false],
    'Missing parameter name' => ['=value', false],
    'Missing array root' => ['[key]=value', false],
    'Unclosed array bracket' => ['data[key=value', false],
    'Unmatched closing bracket' => ['data]=value', false],
    'Trailing text after array key' => ['data[key]suffix=value', false],
    'Nested opening bracket without closing the parent' => ['data[[key]]=value', false],
    'Null byte in parameter root' => ['param%00suffix=value', false],
    'Null byte in array key' => ['data[key%00suffix]=value', false],
]);

it('requires scalar unique values for every reserved API field', function (string $field): void {
    expect(prepare_api_query("{$field}=first&{$field}=second"))->toBeFalse()
        ->and(prepare_api_query("{$field}[]=value"))->toBeFalse()
        ->and(prepare_api_query("{$field}[key]=value"))->toBeFalse();
})->with(['sign', 'authmode', 'pretty', 'ajax', 'function', 'user']);
