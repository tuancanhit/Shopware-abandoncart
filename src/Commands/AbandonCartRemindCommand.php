<?php

namespace AbandonCart\Plugin\Commands;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;

/**
 *   
 *   
 *   
 * Class AbandonCartRemindCommand
 *
 * @package AbandonCartPlugin\Commands
 */
class AbandonCartRemindCommand extends Command
{
    private Connection $connection;
    private SystemConfigService $configService;
    private EntityRepositoryInterface $customerRepository;
    private AbstractSalesChannelContextFactory $channelContextFactory;
    private AbstractMailService $mailService;
    private AbstractCartPersister $cartPersister;
    private EntityRepositoryInterface $mailTemplateRepository;

    private $isEnableds = [];

    /**
     * @param Connection $connection
     * @param SystemConfigService $configService
     * @param EntityRepositoryInterface $customerRepository
     * @param AbstractSalesChannelContextFactory $channelContextFactory
     * @param AbstractMailService $mailService
     * @param AbstractCartPersister $cartPersister
     * @param EntityRepositoryInterface $mailTemplateRepository
     * @param string|null $name
     */
    public function __construct(
        Connection                         $connection,
        SystemConfigService                $configService,
        EntityRepositoryInterface          $customerRepository,
        AbstractSalesChannelContextFactory $channelContextFactory,
        AbstractMailService                $mailService,
        AbstractCartPersister              $cartPersister,
        EntityRepositoryInterface          $mailTemplateRepository,
        string                             $name = null
    )
    {
        parent::__construct($name);
        $this->connection = $connection;
        $this->configService = $configService;
        $this->customerRepository = $customerRepository;
        $this->channelContextFactory = $channelContextFactory;
        $this->mailService = $mailService;
        $this->cartPersister = $cartPersister;
        $this->mailTemplateRepository = $mailTemplateRepository;
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('abandon:cart:remind')
            ->setDescription('Send email remind customer abandon cart');
    }

    /**
     * @param string $salesChannelId
     * @return bool
     */
    protected function isEnable(string $salesChannelId): bool
    {
        if (!isset($this->isEnableds[$salesChannelId])) {
            $status = $this->configService->get('AbandonCartPlugin.config.Enabled', $salesChannelId);
            if (!$status) {
                return $this->isEnableds[$salesChannelId] = false;
            }
            $this->isEnableds[$salesChannelId] = strtolower($status) == 'yes';
        }
        return $this->isEnableds[$salesChannelId];
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $sentQty = $this->process();
            $output->writeln(sprintf("Trigger sent %s abandon cart emails", $sentQty));
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
        }
        return 0;
    }

    /**
     * Todo: Move this function to service class
     *
     * @return int
     * @throws \Exception
     */
    public function process()
    {
        $carts = $this->connection->fetchAll(sprintf('
            SELECT customer.email, cart.token, LOWER(HEX(cart.sales_channel_id)) as sales_channel_id FROM cart as cart
            INNER JOIN customer AS customer ON cart.customer_id = customer.id
            WHERE cart.customer_id IS NOT NULL'
            )
        );

        if (!$carts) {
            throw new \Exception("No more abandon cart");
        }
        $enqueuedCarts = [];
        foreach ($carts as $cart) {
            $enqueued = $this->enqueueAbandonCart($cart['email'], $cart['token'], $cart['sales_channel_id']);
            if (!$enqueued) {
                continue;
            }
            $enqueuedCarts[$cart['email']] = $cart['token'];
        }
        return count($enqueuedCarts);
    }

    /**
     * @param string $email
     * @param string $cartToken
     * @param string $salesChannelId
     * @return bool
     */
    protected function enqueueAbandonCart(string $email, string $cartToken, string $salesChannelId)
    {
        if (!$this->isEnable($salesChannelId)) {
            return false;
        }
        $customer = $this->getCustomer($email);
        $cart = $this->getCustomerCart($cartToken, $salesChannelId);

        return $this->sendMail($salesChannelId, $customer, $cart);
    }

    /**
     * @param string $email
     * @return mixed|null
     */
    protected function getCustomer(string $email)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));
        return $this->customerRepository->search($criteria, Context::createDefaultContext())->first();
    }

    /**
     * @param string $cartToken
     * @param string $salesChannelId
     * @return Cart
     */
    protected function getCustomerCart(string $cartToken, string $salesChannelId)
    {
        $context = $this->getSaleChannelContext($salesChannelId, $cartToken);
        return $this->cartPersister->load($cartToken, $context);
    }

    /**
     * @param string $salesChannelId
     * @param string $token
     * @return SalesChannelContext
     */
    protected function getSaleChannelContext(string $salesChannelId, string $token)
    {
        return $this->channelContextFactory->create(
            $token,
            $salesChannelId,
            []
        );
    }

    /**
     * @param string $salesChannelId
     * @param CustomerEntity $customer
     * @param Cart $cart
     * @return bool
     */
    protected function sendMail(string $salesChannelId, $customer, $cart)
    {
        $templateId = $this->configService->get('AbandonCartPlugin.config.MailTemplate');
        $template = $this->getMailTemplate($templateId);
        $data = new DataBag();
        $data->set(
            'recipients',
            [
                $customer->getEmail() => sprintf('%s %s', $customer->getLastName(), $customer->getFirstName())
            ]
        );
        $data->set('senderName', $template->getTranslation('senderName'));
        $data->set('subject', $template->getTranslation('subject'));
        $data->set('contentHtml', $template->getTranslation('contentHtml'));
        $data->set('contentPlain', $template->getTranslation('contentPlain'));
        $data->set('salesChannelId', $salesChannelId);

        /** @var LineItem[] $cartItems */
        $cartItems = array_values($cart->getLineItems()->getElements());
        $this->mailService->send($data->all(), Context::createDefaultContext(), [
            'customer' => $customer,
            'cart' => $cart,
            'items' => $cartItems,
            'shop_url' => 'https://shopware.local'
        ]);

        return true;
    }

    /**
     * @param string $id
     * @param Context|null $context
     * @return MailTemplateEntity|null
     */
    private function getMailTemplate(string $id, Context $context = null): ?MailTemplateEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->setTitle('send-mail::load-mail-template');
        $criteria->addAssociation('media.media');
        $criteria->setLimit(1);

        return $this->mailTemplateRepository
            ->search($criteria, Context::createDefaultContext())
            ->first();
    }
}
