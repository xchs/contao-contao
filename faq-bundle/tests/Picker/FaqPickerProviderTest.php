<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\FaqBundle\Tests\Picker;

use Contao\BackendUser;
use Contao\CoreBundle\Picker\PickerConfig;
use Contao\FaqBundle\Picker\FaqPickerProvider;
use Contao\TestCase\ContaoTestCase;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuItem;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Translation\TranslatorInterface;

class FaqPickerProviderTest extends ContaoTestCase
{
    /**
     * @var FaqPickerProvider
     */
    private $provider;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $menuFactory = $this->createMock(FactoryInterface::class);
        $menuFactory
            ->method('createItem')
            ->willReturnCallback(
                function (string $name, array $data) use ($menuFactory): ItemInterface {
                    $item = new MenuItem($name, $menuFactory);
                    $item->setLabel($data['label']);
                    $item->setLinkAttributes($data['linkAttributes']);
                    $item->setCurrent($data['current']);
                    $item->setUri($data['uri']);

                    return $item;
                }
            )
        ;

        $router = $this->createMock(RouterInterface::class);
        $router
            ->method('generate')
            ->willReturnCallback(
                function (string $name, array $params): string {
                    return $name.'?'.http_build_query($params);
                }
            )
        ;

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturn('Faq picker')
        ;

        $this->provider = new FaqPickerProvider($menuFactory, $router, $translator);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf('Contao\FaqBundle\Picker\FaqPickerProvider', $this->provider);
    }

    public function testCreatesTheMenuItem(): void
    {
        $picker = json_encode([
            'context' => 'link',
            'extras' => [],
            'current' => 'faqPicker',
            'value' => '',
        ]);

        if (\function_exists('gzencode') && false !== ($encoded = @gzencode($picker))) {
            $picker = $encoded;
        }

        $item = $this->provider->createMenuItem(new PickerConfig('link', [], '', 'faqPicker'));
        $uri = 'contao_backend?do=faq&popup=1&picker='.urlencode(strtr(base64_encode($picker), '+/=', '-_,'));

        $this->assertSame('Faq picker', $item->getLabel());
        $this->assertSame(['class' => 'faqPicker'], $item->getLinkAttributes());
        $this->assertTrue($item->isCurrent());
        $this->assertSame($uri, $item->getUri());
    }

    public function testChecksIfAMenuItemIsCurrent(): void
    {
        $this->assertTrue($this->provider->isCurrent(new PickerConfig('link', [], '', 'faqPicker')));
        $this->assertFalse($this->provider->isCurrent(new PickerConfig('link', [], '', 'filePicker')));
    }

    public function testReturnsTheCorrectName(): void
    {
        $this->assertSame('faqPicker', $this->provider->getName());
    }

    public function testChecksIfAContextIsSupported(): void
    {
        $this->provider->setTokenStorage($this->mockTokenStorage(BackendUser::class));

        $this->assertTrue($this->provider->supportsContext('link'));
        $this->assertFalse($this->provider->supportsContext('file'));
    }

    public function testFailsToCheckTheContextIfThereIsNoTokenStorage(): void
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('No token storage provided');

        $this->provider->supportsContext('link');
    }

    public function testFailsToCheckTheContextIfThereIsNoToken(): void
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->method('getToken')
            ->willReturn(null)
        ;

        $this->provider->setTokenStorage($tokenStorage);

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('No token provided');

        $this->provider->supportsContext('link');
    }

    public function testFailsToCheckTheContextIfThereIsNoUser(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token
            ->method('getUser')
            ->willReturn(null)
        ;

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage
            ->method('getToken')
            ->willReturn($token)
        ;

        $this->provider->setTokenStorage($tokenStorage);

        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('The token does not contain a back end user object');

        $this->provider->supportsContext('link');
    }

    public function testChecksIfAValueIsSupported(): void
    {
        $this->assertTrue($this->provider->supportsValue(new PickerConfig('link', [], '{{faq_url::5}}')));
        $this->assertFalse($this->provider->supportsValue(new PickerConfig('link', [], '{{link_url::5}}')));
    }

    public function testReturnsTheDcaTable(): void
    {
        $this->assertSame('tl_faq', $this->provider->getDcaTable());
    }

    public function testReturnsTheDcaAttributes(): void
    {
        $extra = ['source' => 'tl_faq.2'];

        $this->assertSame(
            [
                'fieldType' => 'radio',
                'preserveRecord' => 'tl_faq.2',
                'value' => '5',
            ],
            $this->provider->getDcaAttributes(new PickerConfig('link', $extra, '{{faq_url::5}}'))
        );

        $this->assertSame(
            [
                'fieldType' => 'radio',
                'preserveRecord' => 'tl_faq.2',
            ],
            $this->provider->getDcaAttributes(new PickerConfig('link', $extra, '{{link_url::5}}'))
        );
    }

    public function testConvertsTheDcaValue(): void
    {
        $this->assertSame('{{faq_url::5}}', $this->provider->convertDcaValue(new PickerConfig('link'), 5));
    }
}
