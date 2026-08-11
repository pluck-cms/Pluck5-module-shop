<?php
declare(strict_types=1);

namespace Pluck\Module\Shop;

use Pluck\Form\Guard;
use Pluck\I18n\Translates;
use Pluck\I18n\Translator;
use Pluck\Module\Insertable;
use Pluck\Module\ModuleView;
use Pluck\Module\PublicForm;
use Pluck\Module\SiteModule;
use Pluck\Security\Escaper;
use Pluck\Site\Urls;
use Pluck\Storage\StorageDriver;

/**
 * Things to order, grouped by category, with a form under them.
 *
 * Written because a scout was selling spring rolls to pay for a Jamboree and
 * then wanted to sell 3D prints too. Those looked like two modules for about
 * five minutes: both are a name, a picture, a description and a price, and both
 * end in somebody typing their address.
 *
 * So one module, and a category decides what a page shows:
 *
 *     [module:shop category=loempias]
 *     [module:shop category=3d-printjes]
 *
 * A third thing to sell is a category, not another module.
 *
 * ## Prices come in steps
 *
 * Spring rolls are sold as 5 for €5, 10 for €9, 20 for €17 — the price per item
 * drops, which is the whole point of buying ten. A 3D print is one thing for one
 * price, which is the same shape with a single step.
 *
 * Each step is orderable on its own, so somebody can take one bag of twenty and
 * two of five without the form having to understand anything about bulk.
 *
 * ## No payment
 *
 * An order is a promise to pay, not a payment. Adding a provider means webhooks,
 * refunds, VAT and a chain of responsibility that does not belong in a CMS you
 * run on the cheapest shared hosting there is — and for a scout selling spring
 * rolls at the door, paying on collection is what actually happens anyway.
 */
final class ShopModule implements SiteModule, Insertable, PublicForm
{
	use Translates;

	/** A form nobody could fill in by accident, and a cap on a silly order. */
	private const MAX_PER_STEP = 99;

	public function __construct(private readonly ?Translator $translator = null)
	{
	}

	public function name(): string
	{
		return 'shop';
	}

	public function mountPath(): string
	{
		return 'shop';
	}

	/**
	 * No page of its own: an order form belongs on the page that explains what
	 * is being sold.
	 *
	 * @param array<string,string> $query
	 */
	public function render(string $path, array $query, StorageDriver $storage, Urls $urls): ?ModuleView
	{
		return null;
	}

	/** @return list<\Pluck\Search\Hit> */
	public function search(string $query, StorageDriver $storage): array
	{
		return [];
	}

	/**
	 * The categories this site has, so the editor can offer them by name.
	 *
	 * @return list<array{label:string,marker:string}>
	 */
	public function embedOptions(StorageDriver $storage): array
	{
		$options = [];

		foreach (self::categories($storage) as $slug => $category) {
			$options[] = [
				'label' => $category['name'],
				'marker' => '[module:shop category=' . $slug . ']',
			];
		}

		if ($options === []) {
			$options[] = [
				'label' => $this->t('shop.insert.none'),
				'marker' => '[module:shop category=NOG-EEN-CATEGORIE-MAKEN]',
			];
		}

		return $options;
	}

	public function embed(array $parameters, StorageDriver $storage, Urls $urls): ?string
	{
		$slug = self::slug($parameters['category'] ?? '');
		$categories = self::categories($storage);

		if ($slug === '' || !isset($categories[$slug])) {
			return '<p class="shop-empty">' . Escaper::html($this->t('shop.no_category')) . '</p>';
		}

		$products = self::products($storage, $slug);

		if ($products === []) {
			return '<p class="shop-empty">' . Escaper::html($this->t('shop.nothing_here')) . '</p>';
		}

		// Read once and handed down: a shop with twenty steps should not ask
		// storage twenty times what a euro looks like.
		return $this->form(
			$categories[$slug],
			$slug,
			$products,
			$urls,
			Money::settings(
				$storage->getModuleData('shop', 'money'),
				// The language this module is speaking, which it already knows.
				$this->translator?->locale()->code ?? '',
			),
		);
	}

	/**
	 * The products, and one form under all of them.
	 *
	 * One form rather than one per product: somebody ordering spring rolls and a
	 * keyring should type their address once.
	 *
	 * @param array{name:string,intro:string} $category
	 * @param list<array<string,mixed>> $products
	 */
	private function form(array $category, string $slug, array $products, Urls $urls, array $format): string
	{
		$sent = ($_GET['besteld'] ?? '') === '1';

		$out = '<div class="shop">';

		if ($sent) {
			// Said above the form rather than instead of it: somebody who ordered
			// once may well order again, and a page that only says "thank you"
			// makes them reload to find out how.
			$out .= '<p class="form-done">' . Escaper::html($this->t('shop.thanks')) . '</p>';
		}

		if ($category['intro'] !== '') {
			$out .= '<p class="shop__intro">' . Escaper::html($category['intro']) . '</p>';
		}

		$out .= '<form class="shop-form" method="post" action="'
			. Escaper::html($urls->to('shop')) . '">';
		$out .= '<input type="hidden" name="categorie" value="' . Escaper::html($slug) . '">';

		$out .= '<ul class="shop__products">';

		foreach ($products as $product) {
			$out .= '<li class="shop-product">';

			if (($product['photo'] ?? '') !== '') {
				$out .= '<img class="shop-product__photo" src="'
					. Escaper::html($urls->media((string) $product['photo']))
					. '" alt="" loading="lazy">';
			}

			$out .= '<div class="shop-product__body">';
			$out .= '<h3 class="shop-product__name">' . Escaper::html((string) $product['name']) . '</h3>';

			if (($product['description'] ?? '') !== '') {
				$out .= '<p class="shop-product__what">' . Escaper::html((string) $product['description']) . '</p>';
			}

			$out .= '<ul class="shop-product__steps">';

			foreach ($product['tiers'] as $index => $tier) {
				$field = 'q_' . $product['id'] . '_' . $index;

				$out .= '<li class="shop-step">';
				$out .= '<label for="' . Escaper::html($field) . '">';
				$out .= Escaper::html(self::stepLabel($tier));
				$out .= ' <span class="shop-step__price">' . Escaper::html(self::money((float) $tier['price'], $format)) . '</span>';
				$out .= '</label>';
				$out .= '<input id="' . Escaper::html($field) . '" name="' . Escaper::html($field) . '"'
					. ' type="number" inputmode="numeric" min="0" max="' . self::MAX_PER_STEP . '" step="1" value="0">';
				$out .= '</li>';
			}

			$out .= '</ul></div></li>';
		}

		$out .= '</ul>';

		$out .= '<h3 class="shop-form__heading">' . Escaper::html($this->t('shop.your_details')) . '</h3>';

		foreach ([
			['name', 'shop.label.name', 'text', true],
			['email', 'shop.label.email', 'email', true],
			['phone', 'shop.label.phone', 'tel', false],
		] as [$field, $key, $type, $required]) {
			$out .= '<div class="field">';
			$out .= '<label for="' . $field . '">' . Escaper::html($this->t($key)) . '</label>';
			$out .= '<input id="' . $field . '" name="' . $field . '" type="' . $type . '"'
				. ($required ? ' required' : '') . ' maxlength="200">';
			$out .= '</div>';
		}

		$out .= '<div class="field">';
		$out .= '<label for="note">' . Escaper::html($this->t('shop.label.note')) . '</label>';
		$out .= '<textarea id="note" name="note" rows="3" maxlength="1000"></textarea>';
		$out .= '</div>';

		$out .= '<button type="submit">' . Escaper::html($this->t('shop.action.order')) . '</button>';
		$out .= '</form></div>';

		return $out;
	}

	/**
	 * An order arrives.
	 *
	 * @param array<string,mixed> $post
	 * @return array{ok:bool,message:string,redirect:?string}
	 */
	public function accept(string $path, array $post, StorageDriver $storage, Urls $urls, Guard $guard): array
	{
		$slug = self::slug((string) ($post['category'] ?? ''));
		$categories = self::categories($storage);

		if ($slug === '' || !isset($categories[$slug])) {
			return ['ok' => false, 'message' => 'shop.no_category', 'redirect' => null];
		}

		$name = self::clean((string) ($post['name'] ?? ''), 200);
		$email = self::clean((string) ($post['email'] ?? ''), 200);
		$phone = self::clean((string) ($post['phone'] ?? ''), 60);
		$note = self::clean((string) ($post['note'] ?? ''), 1000, true);

		if ($name === '' || $email === '') {
			return ['ok' => false, 'message' => 'form.error.fill_it_in', 'redirect' => null];
		}

		if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			return ['ok' => false, 'message' => 'form.error.bad_email', 'redirect' => null];
		}

		/*
		 * The lines, priced from storage and never from the form.
		 *
		 * The page carries the price so somebody can see it; the order is worked
		 * out again from what the site says a thing costs. Otherwise the price is
		 * whatever a posted field claims, and a form that takes the price from
		 * the browser is a shop that sells for nothing.
		 */
		$lines = [];
		$total = 0.0;

		foreach (self::products($storage, $slug) as $product) {
			foreach ($product['tiers'] as $index => $tier) {
				$count = (int) ($post['q_' . $product['id'] . '_' . $index] ?? 0);
				$count = max(0, min(self::MAX_PER_STEP, $count));

				if ($count === 0) {
					continue;
				}

				$price = (float) $tier['price'];
				$total += $price * $count;

				$lines[] = [
					'product' => (string) $product['name'],
					'step' => self::stepLabel($tier),
					'count' => $count,
					'price' => $price,
					'sum' => $price * $count,
				];
			}
		}

		if ($lines === []) {
			return ['ok' => false, 'message' => 'shop.nothing_ordered', 'redirect' => null];
		}

		$id = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));

		/*
		 * Written first, mailed second.
		 *
		 * `mail()` on shared hosting fails in ways nobody finds out about until
		 * somebody asks where their order went. Stored first means the order
		 * exists even when the mail does not.
		 */
		$storage->setModuleData('shop', 'order:' . $id, [
			'id' => $id,
			'category' => $slug,
			'name' => $name,
			'email' => $email,
			'phone' => $phone,
			'note' => $note,
			'lines' => $lines,
			'total' => $total,
			'received_at' => gmdate('c'),
			'handled' => false,
		]);

		$this->mail(
			$storage,
			$categories[$slug]['name'],
			$name,
			$email,
			$phone,
			$note,
			$lines,
			$total,
			Money::settings(
				$storage->getModuleData('shop', 'money'),
				// The language this module is speaking, which it already knows.
				$this->translator?->locale()->code ?? '',
			),
		);

		return [
			'ok' => true,
			'message' => 'shop.thanks',
			'redirect' => $urls->to($path) . '?besteld=1',
		];
	}

	/**
	 * One mail to whoever is selling, one to whoever ordered.
	 *
	 * @param list<array<string,mixed>> $lines
	 */
	private function mail(
		StorageDriver $storage,
		string $category,
		string $name,
		string $email,
		string $phone,
		string $note,
		array $lines,
		float $total,
		array $format,
	): void {
		if (!function_exists('mail')) {
			return;
		}

		$site = (string) $storage->getSetting('site_title', 'Pluck');
		$to = (string) $storage->getSetting('contact_email', '');

		$rows = [];

		foreach ($lines as $line) {
			$rows[] = sprintf(
				'%dx %s — %s = %s',
				$line['count'],
				$line['product'],
				$line['step'],
				self::money((float) $line['sum'], $format),
			);
		}

		$body = implode("\n", [
			$this->t('shop.label.name') . ': ' . $name,
			$this->t('shop.label.email') . ': ' . $email,
			$this->t('shop.label.phone') . ': ' . ($phone !== '' ? $phone : '-'),
			'',
			implode("\n", $rows),
			'',
			$this->t('shop.total') . ': ' . self::money($total, $format),
			'',
			$note !== '' ? $this->t('shop.label.note') . ': ' . $note : '',
		]);

		$subject = $this->t('shop.mail.subject', ['category' => $category, 'site' => $site]);

		/*
		 * The seller's copy first, and never with the customer's address in a
		 * header: a name typed into a form is not a thing to put in From.
		 */
		if ($to !== '') {
			@mail($to, $subject, $body, ['Content-Type' => 'text/plain; charset=UTF-8']);
		}

		@mail($email, $subject, implode("\n", [
			$this->t('shop.mail.thanks', ['name' => $name]),
			'',
			$body,
		]), ['Content-Type' => 'text/plain; charset=UTF-8']);
	}

	// ---- the data -------------------------------------------------------

	/**
	 * Every category, by slug.
	 *
	 * @return array<string,array{name:string,intro:string}>
	 */
	public static function categories(StorageDriver $storage): array
	{
		$out = [];

		foreach ($storage->listModuleData('shop', 'category:') as $key => $value) {
			if (!is_array($value)) {
				continue;
			}

			$slug = substr($key, 9);

			$out[$slug] = [
				'name' => (string) ($value['name'] ?? $slug),
				'intro' => (string) ($value['intro'] ?? ''),
			];
		}

		ksort($out);

		return $out;
	}

	/**
	 * The products of one category, in the order somebody put them in.
	 *
	 * A product with no steps is skipped: it has no price, so there is nothing
	 * to order and a bare name on a shop page is a question, not an offer.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function products(StorageDriver $storage, string $category = ''): array
	{
		$out = [];

		foreach ($storage->listModuleData('shop', 'product:') as $key => $value) {
			if (!is_array($value)) {
				continue;
			}

			$tiers = self::tiers($value['tiers'] ?? null);

			if ($tiers === []) {
				continue;
			}

			if ($category !== '' && (string) ($value['category'] ?? '') !== $category) {
				continue;
			}

			if (($value['active'] ?? true) === false) {
				continue;
			}

			$out[] = [
				'id' => substr($key, 8),
				'category' => (string) ($value['category'] ?? ''),
				'name' => (string) ($value['name'] ?? ''),
				'description' => (string) ($value['description'] ?? ''),
				'photo' => (string) ($value['photo'] ?? ''),
				'order' => (int) ($value['order'] ?? 0),
				'tiers' => $tiers,
			];
		}

		usort($out, static fn (array $a, array $b): int => [$a['order'], $a['name']] <=> [$b['order'], $b['name']]);

		return $out;
	}

	/**
	 * The price steps of a product, cleaned.
	 *
	 * @return list<array{qty:int,price:float,label:string}>
	 */
	public static function tiers(mixed $stored): array
	{
		if (!is_array($stored)) {
			return [];
		}

		$out = [];

		foreach ($stored as $tier) {
			if (!is_array($tier)) {
				continue;
			}

			$price = max(0.0, (float) ($tier['price'] ?? 0));
			$qty = max(1, (int) ($tier['qty'] ?? 1));

			if ($price <= 0.0) {
				continue;
			}

			$out[] = [
				'qty' => $qty,
				'price' => $price,
				'label' => (string) ($tier['label'] ?? ''),
			];
		}

		usort($out, static fn (array $a, array $b): int => $a['qty'] <=> $b['qty']);

		return $out;
	}

	/**
	 * What one step is, with the quantity always visible.
	 *
	 * The label used to replace the quantity rather than describe it, so a step
	 * called "White with carabine" priced at € 20 said nothing about being ten of
	 * them. The first shop to use this had "10x" typed into every label by hand —
	 * which is a person working around software, and the software was wrong.
	 *
	 * A label of "1" or "1x" is dropped rather than doubled: somebody who wrote
	 * the count into the label was doing this job for us, and "1× 1x Wit" reads
	 * like a bug.
	 */
	public static function stepLabel(array $tier): string
	{
		$qty = max(1, (int) ($tier['qty'] ?? 1));
		$label = trim((string) ($tier['label'] ?? ''));

		// A label that already starts with this count keeps its own wording.
		if ($label !== '' && preg_match('/^' . $qty . '\s*[x×]\s*/iu', $label) === 1) {
			return $label;
		}

		if ($label === '') {
			return $qty . '×';
		}

		return $qty . '× ' . $label;
	}

	/**
	 * An amount, written the way this site writes one.
	 *
	 * The symbol belongs here and nowhere else — in another module it was in the
	 * wording as well, and the page read "van € € 4.500,00".
	 *
	 * @param array<string,mixed> $format from Money::settings()
	 */
	public static function money(float $amount, array $format): string
	{
		return Money::format($amount, $format);
	}

	/** A slug that is one, or nothing. */
	public static function slug(string $raw): string
	{
		$clean = preg_replace('/[^a-z0-9-]/', '', strtolower(trim($raw))) ?? '';

		return mb_substr($clean, 0, 60);
	}

	private static function clean(string $value, int $limit, bool $keepNewlines = false): string
	{
		$text = strip_tags($value);
		$text = preg_replace($keepNewlines ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';

		return mb_substr(trim($text), 0, $limit);
	}
}
