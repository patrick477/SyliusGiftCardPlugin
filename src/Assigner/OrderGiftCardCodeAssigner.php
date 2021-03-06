<?php

declare(strict_types=1);

namespace Setono\SyliusGiftCardPlugin\Assigner;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusGiftCardPlugin\EmailManager\GiftCardOrderEmailManagerInterface;
use Setono\SyliusGiftCardPlugin\Factory\GiftCardCodeFactoryInterface;
use Setono\SyliusGiftCardPlugin\Generator\GiftCardCodeGeneratorInterface;
use Setono\SyliusGiftCardPlugin\Repository\GiftCardRepositoryInterface;
use Setono\SyliusGiftCardPlugin\Resolver\GiftCardProductResolverInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;

final class OrderGiftCardCodeAssigner implements OrderGiftCardCodeAssignerInterface
{
    /** @var GiftCardCodeFactoryInterface */
    private $giftCardCodeFactory;

    /** @var GiftCardCodeGeneratorInterface */
    private $giftCardCodeGenerator;

    /** @var GiftCardProductResolverInterface */
    private $giftCardProductResolver;

    /** @var GiftCardRepositoryInterface */
    private $giftCardRepository;

    /** @var GiftCardOrderEmailManagerInterface */
    private $giftCardOrderEmailManager;

    /** @var EntityManagerInterface|EntityManager */
    private $giftCardEntityManager;

    public function __construct(
        GiftCardCodeFactoryInterface $giftCardCodeFactory,
        GiftCardCodeGeneratorInterface $giftCardCodeGenerator,
        GiftCardProductResolverInterface $giftCardProductResolver,
        GiftCardRepositoryInterface $giftCardRepository,
        GiftCardOrderEmailManagerInterface $giftCardOrderEmailManager,
        EntityManagerInterface $giftCardEntityManager
    ) {
        $this->giftCardCodeFactory = $giftCardCodeFactory;
        $this->giftCardCodeGenerator = $giftCardCodeGenerator;
        $this->giftCardProductResolver = $giftCardProductResolver;
        $this->giftCardRepository = $giftCardRepository;
        $this->giftCardOrderEmailManager = $giftCardOrderEmailManager;
        $this->giftCardEntityManager = $giftCardEntityManager;
    }

    public function assignGiftCardCode(OrderInterface $order): void
    {
        $giftCardCodes = [];

        /** @var OrderItemInterface $orderItem */
        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();

            if (true === $this->giftCardProductResolver->isGiftCardProduct($product)) {
                $giftCard = $this->giftCardRepository->findOneByProduct($product);

                for ($i = 0; $i < $orderItem->getQuantity(); ++$i) {
                    $giftCardCode = $this->giftCardCodeFactory->createWithGiftCardAndOrderItem($giftCard, $orderItem);

                    $giftCardCode->setAmount($orderItem->getUnitPrice());
                    $giftCardCode->setChannelCode($order->getChannel()->getCode());
                    $giftCardCode->setCode($this->giftCardCodeGenerator->generate());
                    $giftCardCode->setIsActive(true);

                    $this->giftCardEntityManager->persist($giftCardCode);
                    $this->giftCardEntityManager->flush($giftCardCode);

                    $giftCardCodes[] = $giftCardCode;
                }
            }
        }

        if (count($giftCardCodes) > 0) {
            $this->giftCardOrderEmailManager->sendEmailWithGiftCardCodes($order, $giftCardCodes);
        }
    }
}
