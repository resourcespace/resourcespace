<?php

function GetStem($word) {return NorwegianStemmer::stem($word);}

mb_internal_encoding("utf-8");


/**
* Copyright (c) 2014 Tom Erik Støwer (http://github.com/testower)
*
* All rights reserved.
*
* This script is free software.
*/

/**
* PHP5 Implementation of the Snowball Norwegian stemming algorithm
*  - http://snowball.tartarus.org/algorithms/norwegian/stemmer.html
*
* Usage:
*
*  $stem = NorwegianStemmer::Stem($word);
*
*/

class NorwegianStemmer
{
    /**
    * Regex for matching a vowel
    * @var string
    */
    private static $regex_vowels = '(?:[aeiouyæåø])';

    /**
    * Regex for matching a non-vowel
    * @var string
    */
    private static $regex_non_vowels = '(?:[^aeiouyæåø])';

    /**
    * Stems a word.
    *
    * @param  string $word Word to stem
    * @return string       Stemmed word
    */
    public static function Stem($word)
    {
        if (strlen($word) <= 2) {
            return $word;
        }

        $word = self::step1($word);
        $word = self::step2($word);
        $word = self::step3($word);

        return $word;
    }

    /**
    * Step 1
    *
    * @param string $word Word to stem
    */
    private static function step1($word)
    {
        $a = '(?:a|e|ede|ande|ende|ane|ene|hetene|en|heten|ar|er|heter|as|es|edes|endes|enes|hetenes|ens|hetens|ers|ets|et|het|ast)$';
        $b = '(?:s)$';
        $c = '(?:erte|ert)$';

        $r1 = self::r1($word);

        if (self::count($r1) == 0) {
            return $word;
        }

        $matches_a_endings = array();
        $matches_b_endings = array();
        $matches_c_endings = array();

        preg_match("#$a$#", $r1, $matches_a_endings);
        preg_match("#$b$#", $r1, $matches_b_endings);
        preg_match("#$c$#", $r1, $matches_c_endings);


        $longest_a_match = '';
        foreach ($matches_a_endings as &$a_match) {

            if (self::count($a_match) > self::count($longest_a_match)) {
                $longest_a_match = $a_match;
            }
        }

        $longest_b_match = '';
        foreach ($matches_b_endings as &$b_match) {

            if (self::count($b_match) > self::count($longest_b_match)) {
                $longest_b_match = $b_match;
            }
        }

        $longest_c_match = '';
        foreach ($matches_c_endings as &$c_match) {

            if (self::count($c_match) > self::count($longest_c_match)) {
                $longest_c_match = $c_match;
            }
        }

        if (self::count($longest_a_match) > self::count($longest_b_match)
            && self::count($longest_a_match) > self::count($longest_c_match)) {

            self::replace($word, $longest_a_match, '');

        } elseif (self::count($longest_b_match) > self::count($longest_a_match)
            && self::count($longest_b_match) > self::count($longest_c_match)) {

            $s = '(?:[bcdfghjlmnoprtvyz]|[^aeiouyæåø]k)';
            $l = $longest_b_match;

            if (preg_match("#($s)$l$#", $word)) {
                self::replace($word, $longest_b_match, '');
            }

        } elseif (self::count($longest_c_match) > self::count($longest_a_match)
            && self::count($longest_c_match) > self::count($longest_b_match)) {

            self::replace($word, $longest_c_match, 'er');
        }

        return $word;
    }

    /**
    * Step 2
    *
    * @param string $word Word to stem
    */
    private static function step2($word)
    {
        $d = "(?:dt|vt)$";
        $r1 = self::r1($word);

        if (preg_match("#$d#", $r1) > 0) {
            self::replace($word, 't', '');
        }

        return $word;
    }

    /**
    * Step 3
    *
    * @param string $word Word to stem
    */
    private static function step3($word)
    {
        $r1 = self::r1($word);

        $e = "(?:leg|eleg|ig|eig|lig|elig|els|lov|elov|slov|hetslov)$";

        $e_matches = array();

        if (preg_match("#$e#", $r1, $e_matches) > 0) {
            $longest_e_match = '';
            foreach ($e_matches as &$e_match) {
                if (self::count($e_match) > self::count($longest_e_match)) {
                    $longest_e_match = $e_match;
                }
            }
            self::replace($word, $longest_e_match, '');
        }

        return $word;
    }

    /** Finds the word's suffix defined as the part after the first
    *   non-vowel after the first vowel in the word
    *
    * @param string $word   Word to check
    * @return string        r1-suffix
    *
    */
    private static function r1($word) {
        $v = self::$regex_vowels;
        $nv = self::$regex_non_vowels;

        $substrings = preg_split("#$v+$nv#", $word, 2);

        if (count($substrings) < 2) {
            return '';
        }

        $r1 = $substrings[1];
        $r1_escaped = implode('\#', explode('#', $r1));

        while (self::count($r1) > 0 && self::count(preg_replace("#$r1_escaped$#", '', $word)) < 3) {
            $r1 = substr($r1, 1);
        }

        return $r1;
    }

    /** Counts multibyte characters in a string
    *
    * @param string $string String to check
    * @return int           Number of multibyte characters
    *
    */
    private static function count($string) {
        // split into characters (not bytes, like explode() or str_split() would)
        $characters = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY)?:[];
        // count what's left
        return count($characters);
    }

    /**
    * The following to methods are shamelessly borrowed from @camspiers
    * - https://github.com/camspiers/porter-stemmer/
    */

    /**
    * Replaces the first string with the second, at the end of the string. If third
    * arg is given, then the preceding string must match that m count at least.
    *
    * @param  string $str   String to check
    * @param  string $check Ending to check for
    * @param  string $repl  Replacement string
    * @param  int    $m     Optional minimum number of m() to meet
    * @return bool          Whether the $check string was at the end
    *                       of the $str string. True does not necessarily mean
    *                       that it was replaced.
    */
    private static function replace(&$str, $check, $repl, $m = null)
    {
        $len = 0 - strlen($check);

        if (substr($str, $len) == $check) {
            $substr = substr($str, 0, $len);
            if (is_null($m) OR self::m($substr) > $m) {
                $str = $substr . $repl;
            }

            return true;
        }

        return false;
    }

    /**
    * What, you mean it's not obvious from the name?
    *
    * m() measures the number of consonant sequences in $str. if c is
    * a consonant sequence and v a vowel sequence, and <..> indicates arbitrary
    * presence,
    *
    * <c><v>       gives 0
    * <c>vc<v>     gives 1
    * <c>vcvc<v>   gives 2
    * <c>vcvcvc<v> gives 3
    *
    * @param  string $str The string to return the m count for
    * @return int         The m count
    */
    private static function m($str)
    {
        $c = self::$regex_non_vowels;
        $v = self::$regex_vowels;

        $str = preg_replace("#^$c+#", '', $str);
        $str = preg_replace("#$v+$#", '', $str);

        preg_match_all("#($v+$c+)#", $str, $matches);

        return count($matches[1]);
    }
}