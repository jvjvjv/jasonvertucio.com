<?php

namespace Tests\Unit\Services\Resume\Diff;

use App\Services\Resume\Diff\WordDiff;
use PHPUnit\Framework\TestCase;

class WordDiffTest extends TestCase
{
    public function test_a_swapped_word_is_one_deletion_and_one_insertion(): void
    {
        $this->assertSame([
            ['op' => 'equal', 'text' => 'Led a team of '],
            ['op' => 'delete', 'text' => '4'],
            ['op' => 'insert', 'text' => '6'],
            ['op' => 'equal', 'text' => ' engineers'],
        ], WordDiff::tokens('Led a team of 4 engineers', 'Led a team of 6 engineers'));
    }

    public function test_an_inserted_phrase_is_one_insertion(): void
    {
        $this->assertSame([
            ['op' => 'equal', 'text' => 'Built '],
            ['op' => 'insert', 'text' => 'and shipped '],
            ['op' => 'equal', 'text' => 'the API.'],
        ], WordDiff::tokens('Built the API.', 'Built and shipped the API.'));
    }

    public function test_a_deleted_word_and_its_punctuation_are_one_deletion(): void
    {
        $this->assertSame([
            ['op' => 'equal', 'text' => 'Fast'],
            ['op' => 'delete', 'text' => ', cheap'],
            ['op' => 'equal', 'text' => ' and good'],
        ], WordDiff::tokens('Fast, cheap and good', 'Fast and good'));
    }

    public function test_identical_strings_are_one_equal_segment(): void
    {
        $this->assertSame([['op' => 'equal', 'text' => 'Same text']], WordDiff::tokens('Same text', 'Same text'));
        $this->assertSame([], WordDiff::tokens('', '  '));
    }

    public function test_a_formatting_only_change_has_no_insertions_or_deletions(): void
    {
        $this->assertSame(
            [['op' => 'equal', 'text' => 'Expert in Laravel']],
            WordDiff::tokens('Expert in <b>Laravel</b>', 'Expert in <strong class="x">Laravel</strong>'),
        );
    }

    public function test_a_field_above_the_cap_is_replaced_whole(): void
    {
        $long = str_repeat('word ', WordDiff::MAX_TOKENS);

        $this->assertSame([
            ['op' => 'delete', 'text' => trim($long)],
            ['op' => 'insert', 'text' => 'short'],
        ], WordDiff::tokens($long, 'short'));
    }

    public function test_tokens_reassemble_both_sides(): void
    {
        $before = "It's the team’s best quarter—by far.";
        $after = "It's our team’s best year, by far!";
        $segments = WordDiff::tokens($before, $after);

        $side = fn (string $skip): string => implode('', array_column(array_filter($segments, fn (array $s): bool => $s['op'] !== $skip), 'text'));

        $this->assertSame($before, $side('insert'));
        $this->assertSame($after, $side('delete'));
        $this->assertContains(['op' => 'equal', 'text' => "It's "], $segments);
    }

    public function test_split_keeps_words_whitespace_and_punctuation_apart(): void
    {
        $this->assertSame(['Hi', ',', ' ', 'it', ' ', 'works', '.'], WordDiff::split('Hi, it works.'));
    }

    public function test_elide_shortens_long_unchanged_stretches_around_a_change(): void
    {
        $words = fn (int $from, int $to): string => implode(' ', array_map(fn (int $n): string => "w{$n}", range($from, $to)));

        $elided = WordDiff::elide([
            ['op' => 'equal', 'text' => $words(1, 30).' '],
            ['op' => 'insert', 'text' => 'new'],
            ['op' => 'equal', 'text' => ' '.$words(31, 60).' '],
            ['op' => 'delete', 'text' => 'old'],
            ['op' => 'equal', 'text' => ' short tail'],
        ], 3);

        $this->assertSame('… w28 w29 w30 ', $elided[0]['text']);
        $this->assertSame(' w31 w32 w33 … w58 w59 w60 ', $elided[2]['text']);
        $this->assertSame(' short tail', $elided[4]['text']);
        $this->assertSame('new', $elided[1]['text']);
    }
}
