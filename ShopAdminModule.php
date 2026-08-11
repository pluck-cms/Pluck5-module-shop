<?php
declare(strict_types=1);

namespace Pluck\Module\Shop;

use Pluck\Http\Router;
use Pluck\Module\AdminModule;
use Pluck\Module\ModuleContext;
use Pluck\Module\ModulePermission;

/**
 * Managing what is for sale, and reading what came in.
 */
final class ShopAdminModule implements AdminModule
{
	public function name(): string
	{
		return 'shop';
	}

	public function adminRoutes(Router $router): void
	{
		$permission = ModulePermission::manage('shop');

		$router->get('module.shop.index', ShopAdminController::class, 'index', $permission, module: 'shop');
		$router->post('module.shop.category', ShopAdminController::class, 'category', $permission, module: 'shop');
		$router->post('module.shop.category.remove', ShopAdminController::class, 'removeCategory', $permission, module: 'shop');
		$router->get('module.shop.product', ShopAdminController::class, 'product', $permission, module: 'shop');
		$router->post('module.shop.product.save', ShopAdminController::class, 'saveProduct', $permission, module: 'shop');
		$router->post('module.shop.product.remove', ShopAdminController::class, 'removeProduct', $permission, module: 'shop');
		$router->get('module.shop.orders', ShopAdminController::class, 'orders', $permission, module: 'shop');
		$router->post('module.shop.order.handled', ShopAdminController::class, 'handled', $permission, module: 'shop');
		$router->post('module.shop.money', ShopAdminController::class, 'money', $permission, module: 'shop');
	}

	public function navigation(): ?array
	{
		return [
			'route' => 'module.shop.index',
			'label' => 'shop.title',
			'permission' => ModulePermission::manage('shop'),
		];
	}

	public function viewDir(): ?string
	{
		return __DIR__ . '/views';
	}
}

/**
 * Categories, products with their price steps, and the orders.
 */
final class ShopAdminController
{
	/** Enough steps for any bulk deal, and a stop before a form gets silly. */
	private const MAX_TIERS = 8;

	public function __construct(private readonly ModuleContext $c)
	{
	}

	public function index(): never
	{
		$this->c->render('shop/index', [
			'title' => $this->c->t('shop.title'),
			'categories' => $this->categories(),
			'products' => $this->allProducts(),
			'orders' => count($this->c->list('order:')),
			// How this site writes an amount. Kept by this module: a module
			// cannot reach another module's settings, and that isolation is what
			// stops one from setting `theme` — not worth widening for a symbol.
			'money' => Money::settings($this->c->get('money'), $this->c->locale()),
		]);
	}

	public function category(): never
	{
		$request = $this->c->request;

		$name = mb_substr(trim(strip_tags($request->post('name', ''))), 0, 80);
		$slug = ShopModule::slug($request->post('slug', '') !== ''
			? $request->post('slug', '')
			: str_replace(' ', '-', strtolower($name)));

		if ($name === '' || $slug === '') {
			$this->c->flash->stop($this->c->t('shop.category.needs_name'));
			$this->c->back('module.shop.index');
		}

		$this->c->set('category:' . $slug, [
			'name' => $name,
			'intro' => mb_substr(trim(strip_tags($request->post('intro', ''))), 0, 500),
		]);

		$this->c->flash->ok($this->c->t('shop.category.saved'));
		$this->c->back('module.shop.index');
	}

	public function removeCategory(): never
	{
		$slug = ShopModule::slug($this->c->request->post('slug', ''));

		/*
		 * A category with products in it is not removed.
		 *
		 * Deleting it would leave those products pointing at a category that does
		 * not exist — they would vanish from every page while still sitting in
		 * storage, which is the kind of disappearance nobody can explain later.
		 */
		foreach ($this->c->list('product:') as $value) {
			if (is_array($value) && (string) ($value['category'] ?? '') === $slug) {
				$this->c->flash->stop($this->c->t('shop.category.in_use'));
				$this->c->back('module.shop.index');
			}
		}

		$this->c->delete('category:' . $slug);
		$this->c->flash->ok($this->c->t('shop.category.removed'));
		$this->c->back('module.shop.index');
	}

	public function product(): never
	{
		$id = preg_replace('/[^a-z0-9-]/', '', $this->c->request->query('id', '')) ?? '';
		$stored = $id !== '' ? $this->c->get('product:' . $id) : null;

		$this->c->render('shop/product', [
			'title' => $this->c->t($id === '' ? 'shop.product.new' : 'shop.product.edit'),
			'id' => $id,
			'categories' => $this->categories(),
			'media' => $this->c->media(),
			'product' => is_array($stored) ? $stored : [
				'name' => '', 'description' => '', 'photo' => '',
				'category' => '', 'order' => 0, 'active' => true,
			],
			'tiers' => ShopModule::tiers(is_array($stored) ? ($stored['tiers'] ?? null) : null),
			'maxTiers' => self::MAX_TIERS,
		]);
	}

	public function saveProduct(): never
	{
		$request = $this->c->request;

		$id = preg_replace('/[^a-z0-9-]/', '', $request->post('id', '')) ?? '';
		if ($id === '') {
			$id = bin2hex(random_bytes(6));
		}

		$name = mb_substr(trim(strip_tags($request->post('name', ''))), 0, 120);

		if ($name === '') {
			$this->c->flash->stop($this->c->t('shop.product.needs_name'));
			$this->c->back('module.shop.index');
		}

		/*
		 * The steps, read as pairs and kept only when they are one.
		 *
		 * An empty row is how somebody leaves a step out, so it is skipped rather
		 * than refused — a form that will not save because row four is blank is a
		 * form people learn to fight.
		 */
		$tiers = [];

		for ($i = 0; $i < self::MAX_TIERS; $i++) {
			$price = self::amount($request->post('tier_price_' . $i, ''));

			if ($price <= 0.0) {
				continue;
			}

			$tiers[] = [
				'qty' => max(1, (int) $request->post('tier_qty_' . $i, '1')),
				'price' => $price,
				'label' => mb_substr(trim(strip_tags($request->post('tier_label_' . $i, ''))), 0, 60),
			];
		}

		$photo = basename($request->post('photo', ''));

		$this->c->set('product:' . $id, [
			'name' => $name,
			'description' => mb_substr(trim(strip_tags($request->post('description', ''))), 0, 1000),
			// Only a file this module owns: a name typed into the form would
			// otherwise be printed as an image source on a public page.
			'photo' => in_array($photo, $this->c->media(), true) ? $photo : '',
			'category' => ShopModule::slug($request->post('category', '')),
			'order' => max(0, min(999, (int) $request->post('order', '0'))),
			'active' => $request->post('active', '') !== '',
			'tiers' => $tiers,
		]);

		$this->c->flash->ok($this->c->t('shop.product.saved'));
		$this->c->back('module.shop.index');
	}

	public function removeProduct(): never
	{
		$id = preg_replace('/[^a-z0-9-]/', '', $this->c->request->post('id', '')) ?? '';

		if ($id !== '') {
			$this->c->delete('product:' . $id);
			$this->c->flash->ok($this->c->t('shop.product.removed'));
		}

		$this->c->back('module.shop.index');
	}

	/** How amounts are written here. */
	public function money(): never
	{
		$request = $this->c->request;

		$this->c->set('money', [
			'symbol' => $request->post('symbol', ''),
			'after' => $request->post('after', '') !== '',
			'decimals' => (int) $request->post('decimals', '2'),
			'decimal_point' => $request->post('decimal_point', ''),
			'thousands' => $request->post('thousands', ''),
		]);

		$this->c->flash->ok($this->c->t('shop.money.saved'));
		$this->c->back('module.shop.index');
	}

	public function orders(): never
	{
		$orders = [];

		foreach ($this->c->list('order:') as $value) {
			if (is_array($value)) {
				$orders[] = $value;
			}
		}

		// Newest first: an order from this morning matters more than one from
		// last March.
		usort($orders, static fn (array $a, array $b): int
			=> (string) ($b['received_at'] ?? '') <=> (string) ($a['received_at'] ?? ''));

		$this->c->render('shop/orders', [
			'title' => $this->c->t('shop.orders.title'),
			'orders' => $orders,
			'money' => Money::settings($this->c->get('money'), $this->c->locale()),
		]);
	}

	public function handled(): never
	{
		$id = preg_replace('/[^A-Za-z0-9-]/', '', $this->c->request->post('id', '')) ?? '';
		$order = $id !== '' ? $this->c->get('order:' . $id) : null;

		if (is_array($order)) {
			$order['handled'] = !($order['handled'] ?? false);
			$this->c->set('order:' . $id, $order);
		}

		$this->c->back('module.shop.orders');
	}

	// ---- helpers --------------------------------------------------------

	/** @return array<string,array{name:string,intro:string}> */
	private function categories(): array
	{
		$out = [];

		foreach ($this->c->list('category:') as $key => $value) {
			if (is_array($value)) {
				$out[substr($key, 9)] = [
					'name' => (string) ($value['name'] ?? ''),
					'intro' => (string) ($value['intro'] ?? ''),
				];
			}
		}

		ksort($out);

		return $out;
	}

	/**
	 * Every product, including the ones switched off and the ones without a
	 * price — the site hides those, and the admin is where you go to find out
	 * why something is not showing.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function allProducts(): array
	{
		$out = [];

		foreach ($this->c->list('product:') as $key => $value) {
			if (!is_array($value)) {
				continue;
			}

			$out[] = $value + [
				'id' => substr($key, 8),
				'tiers' => ShopModule::tiers($value['tiers'] ?? null),
			];
		}

		usort($out, static fn (array $a, array $b): int
			=> [(string) $a['category'], (int) ($a['order'] ?? 0), (string) $a['name']]
			<=> [(string) $b['category'], (int) ($b['order'] ?? 0), (string) $b['name']]);

		return $out;
	}

	/**
	 * Reads "5,50", "1.250,00" and "5.50" as the same number.
	 *
	 * On a Dutch keyboard "5,50" is the natural thing to type and (float) reads
	 * it as 5 — the cents vanish silently, which for a price is the difference
	 * between selling and giving away.
	 */
	private static function amount(string $typed): float
	{
		$clean = preg_replace('/[^0-9.,]/', '', $typed) ?? '';

		$comma = strrpos($clean, ',');
		$dot = strrpos($clean, '.');

		if ($comma !== false && ($dot === false || $comma > $dot)) {
			$clean = str_replace('.', '', $clean);
			$clean = str_replace(',', '.', $clean);
		} else {
			$clean = str_replace(',', '', $clean);
		}

		return max(0.0, min(99999.0, (float) $clean));
	}
}
