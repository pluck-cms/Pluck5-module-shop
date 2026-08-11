<?php
declare(strict_types=1);

namespace Pluck\Module\Shop;

/**
 * How an amount is written.
 *
 * Not a money library: no exchange rates, no locale database, no rounding rules
 * for invoices. Four questions and nothing else — which symbol, which side it
 * goes on, how many decimals, and which separators.
 *
 * ## Why two decimals is not a safe assumption
 *
 * Most currencies have a minor unit of 1/100 and it is tempting to hard-code
 * that. The yen has none — ¥1200, no sen since 1953 — and the Kuwaiti, Bahraini
 * and Omani dinars have three. A site in Tokyo writing "¥ 1.200,00" looks like
 * software that was never asked.
 *
 * ## Kept by this module, not by the site
 *
 * A site that sells things and keeps a ledger has one currency, so one shared
 * setting would be right — and a module deliberately cannot reach site settings.
 * That isolation is what stops a module from setting `theme` or
 * `modules_enabled`, and it is not worth widening for a currency symbol.
 *
 * So each module keeps its own. Run this alongside the shop module and the
 * symbol has to be set in both; the READMEs say so. That is the honest cost of
 * modules that cannot reach into each other.
 */
final class Money
{
	/**
	 * Separators, per language this module speaks.
	 *
	 * Derived from the site's language, which the module already knows: it is
	 * handed a translator, and a translator knows its locale.
	 *
	 * Only the separators. **Language is not currency** — English is the pound,
	 * the dollar and the euro, and guessing a symbol from a language is how a
	 * Dublin shop ends up priced in sterling.
	 */
	private const BY_LANGUAGE = [
		'nl' => ['decimal_point' => ',', 'thousands' => '.'],
		'pl' => ['decimal_point' => ',', 'thousands' => ' '],
		'en' => ['decimal_point' => '.', 'thousands' => ','],
	];

	/** Euro, as the sites this was written for use it. One field to change. */
	private const DEFAULTS = [
		'symbol' => '€',
		'after' => false,
		'decimals' => 2,
		'decimal_point' => ',',
		'thousands' => '.',
	];

	/**
	 * An amount, written the way this site writes one.
	 *
	 * @param array<string,mixed> $format from settings()
	 */
	public static function format(float $amount, array $format): string
	{
		$f = $format;

		$number = number_format(
			$amount,
			(int) $f['decimals'],
			(string) $f['decimal_point'],
			(string) $f['thousands'],
		);

		// A space between symbol and number, because "€1.200,00" is a price tag
		// and this is prose. Somebody who wants it tight can say so in the symbol.
		return $f['after'] === true
			? $number . ' ' . $f['symbol']
			: $f['symbol'] . ' ' . $number;
	}

	/**
	 * How this site writes money, with everything filled in.
	 *
	 * Read once and passed down where a page formats many amounts: a table of
	 * forty lines should not ask storage forty times.
	 *
	 * @param mixed $stored whatever was kept under this module's `money` key
	 * @param string $language the site's language, for the separators
	 * @return array{symbol:string,after:bool,decimals:int,decimal_point:string,thousands:string}
	 */
	public static function settings(mixed $stored, string $language = ''): array
	{
		$stored = is_array($stored) ? $stored : [];

		// The site's language decides the separators until somebody says
		// otherwise. A language this module does not speak keeps the defaults.
		$defaults = (self::BY_LANGUAGE[$language] ?? []) + self::DEFAULTS;

		return [
			'symbol' => self::text($stored['symbol'] ?? null, $defaults['symbol'], 8),
			'after' => (bool) ($stored['after'] ?? $defaults['after']),
			// Nothing above three: no currency has more, and a number with six
			// decimals in a shop is a mistake somebody typed.
			'decimals' => max(0, min(3, (int) ($stored['decimals'] ?? $defaults['decimals']))),
			'decimal_point' => self::text($stored['decimal_point'] ?? null, $defaults['decimal_point'], 1),
			'thousands' => self::text($stored['thousands'] ?? null, $defaults['thousands'], 1, true),
		];
	}

	/**
	 * A separator or a symbol, as text and nothing else.
	 *
	 * These end up in HTML. Tags are stripped rather than trusted to whoever
	 * prints it — every caller escapes, and this makes that a guarantee rather
	 * than a habit.
	 *
	 * The thousands separator may be empty, which is how somebody writes 1200
	 * without a break; the others fall back to the default when cleared, because
	 * a currency with no symbol and no decimal point is not a choice anybody
	 * meant to make.
	 */
	private static function text(mixed $value, string $fallback, int $limit, bool $mayBeEmpty = false): string
	{
		if (!is_string($value)) {
			return $fallback;
		}

		$clean = strip_tags($value);
		$clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $clean) ?? '';
		$clean = mb_substr($clean, 0, $limit);

		if ($clean === '' && !$mayBeEmpty) {
			return $fallback;
		}

		return $clean;
	}
}
