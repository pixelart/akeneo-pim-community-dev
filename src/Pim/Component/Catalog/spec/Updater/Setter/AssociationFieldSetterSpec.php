<?php

namespace spec\Pim\Component\Catalog\Updater\Setter;

use Akeneo\Component\StorageUtils\Repository\IdentifiableObjectRepositoryInterface;
use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Pim\Component\Catalog\Builder\ProductBuilderInterface;
use Pim\Component\Catalog\Exception\InvalidArgumentException;
use Pim\Component\Catalog\Model\AssociationInterface;
use Pim\Component\Catalog\Model\AssociationTypeInterface;
use Pim\Component\Catalog\Model\GroupInterface;
use Pim\Component\Catalog\Model\ProductInterface;

class AssociationFieldSetterSpec extends ObjectBehavior
{
    function let(
        IdentifiableObjectRepositoryInterface $productRepository,
        IdentifiableObjectRepositoryInterface $groupRepository,
        ProductBuilderInterface $productBuilder
    ) {
        $this->beConstructedWith($productRepository, $groupRepository, $productBuilder, ['associations']);
    }

    function it_is_a_setter()
    {
        $this->shouldImplement('Pim\Component\Catalog\Updater\Setter\SetterInterface');
        $this->shouldImplement('Pim\Component\Catalog\Updater\Setter\FieldSetterInterface');
    }

    function it_supports_associations_field()
    {
        $this->supportsField('associations')->shouldReturn(true);
        $this->supportsField('groups')->shouldReturn(false);
    }

    function it_checks_valid_association_data_format(ProductInterface $product)
    {
        $this->shouldThrow(
            InvalidArgumentException::arrayExpected(
                'associations',
                'setter',
                'association',
                'string'
            )
        )->during('setFieldData', [$product, 'associations', 'not an array']);

        $this->shouldThrow(
            InvalidArgumentException::associationFormatExpected(
                'associations',
                [0 => []]
            )
        )->during('setFieldData', [$product, 'associations', [0 => []]]);

        $this->shouldThrow(
            InvalidArgumentException::associationFormatExpected(
                'associations',
                ['assoc_type_code' => []]
            )
        )->during('setFieldData', [$product, 'associations', ['assoc_type_code' => []]]);

        $this->shouldThrow(
            InvalidArgumentException::associationFormatExpected(
                'associations',
                ['assoc_type_code' => ['products' => [1], 'groups' => []]]
            )
        )->during(
            'setFieldData',
            [$product, 'associations', ['assoc_type_code' => ['products' => [1], 'groups' => []]]]
        );

        $this->shouldThrow(
            InvalidArgumentException::associationFormatExpected(
                'associations',
                ['assoc_type_code' => ['products' => [], 'groups' => [2]]]
            )
        )->during(
            'setFieldData',
            [$product, 'associations', ['assoc_type_code' => ['products' => [], 'groups' => [2]]]]
        );
    }

    function it_sets_association_field(
        $productRepository,
        $groupRepository,
        $productBuilder,
        ProductInterface $product,
        AssociationInterface $xsellAssociation,
        AssociationInterface $upsellAssociation,
        ProductInterface $assocProductOne,
        ProductInterface $assocProductTwo,
        ProductInterface $assocProductThree,
        GroupInterface $assocGroupOne,
        GroupInterface $assocGroupTwo,
        AssociationTypeInterface $xsellAssociationType,
        AssociationTypeInterface $upsellAssociationType
    ) {
        $xsellAssociation->getAssociationType()->willReturn($xsellAssociationType);
        $xsellAssociation->getGroups()->willReturn(new ArrayCollection());
        $xsellAssociation->getProducts()->willReturn(new ArrayCollection());
        $upsellAssociation->getAssociationType()->willReturn($upsellAssociationType);
        $upsellAssociation->getGroups()->willReturn(new ArrayCollection());
        $upsellAssociation->getProducts()->willReturn(new ArrayCollection());

        $product->getAssociations()->willReturn(
            new ArrayCollection([$xsellAssociation->getWrappedObject(), $upsellAssociation->getWrappedObject()])
        );

        $productBuilder->addMissingAssociations($product)->shouldBeCalled();
        $product->getAssociationForTypeCode('xsell')->willReturn($xsellAssociation);
        $product->getAssociationForTypeCode('upsell')->willReturn($upsellAssociation);

        $productRepository->findOneByIdentifier('assocProductOne')->willReturn($assocProductOne);
        $productRepository->findOneByIdentifier('assocProductTwo')->willReturn($assocProductTwo);
        $productRepository->findOneByIdentifier('assocProductThree')->willReturn($assocProductThree);

        $groupRepository->findOneByIdentifier('assocGroupOne')->willReturn($assocGroupOne);
        $groupRepository->findOneByIdentifier('assocGroupTwo')->willReturn($assocGroupTwo);

        $xsellAssociation->addProduct($assocProductOne)->shouldBeCalled();
        $xsellAssociation->addProduct($assocProductTwo)->shouldBeCalled();
        $xsellAssociation->addGroup($assocGroupOne)->shouldBeCalled();

        $upsellAssociation->addProduct($assocProductThree)->shouldBeCalled();
        $upsellAssociation->addGroup($assocGroupTwo)->shouldBeCalled();

        $this->setFieldData(
            $product,
            'associations',
            [
                'xsell' => [
                    'products' => ['assocProductOne', 'assocProductTwo'],
                    'groups' => ['assocGroupOne']
                ],
                'upsell' => [
                    'products' => ['assocProductThree'],
                    'groups' => ['assocGroupTwo']
                ]
            ]
        );
    }

    function it_fails_if_one_of_the_association_type_code_does_not_exist(
        $productBuilder,
        ProductInterface $product
    ) {
        $product->getAssociations()->willReturn(new ArrayCollection());
        $productBuilder->addMissingAssociations($product)->shouldBeCalled();
        $product->getAssociationForTypeCode('non valid association type code')->willReturn(null);

        $this->shouldThrow(
            InvalidArgumentException::expected(
                'associations',
                'existing association type code',
                'setter',
                'association',
                'non valid association type code'
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                ['non valid association type code' => ['groups' => [], 'products' => []]]
            ]
        );
    }

    function it_fails_if_one_of_the_associated_product_does_not_exist(
        $productBuilder,
        $productRepository,
        ProductInterface $product,
        AssociationInterface $xsellAssociation,
        AssociationTypeInterface $associationType
    ) {
        $xsellAssociation->getAssociationType()->willReturn($associationType);
        $xsellAssociation->getGroups()->willReturn(new ArrayCollection());
        $xsellAssociation->getProducts()->willReturn(new ArrayCollection());

        $product->getAssociations()->willReturn(new ArrayCollection([$xsellAssociation->getWrappedObject()]));

        $productBuilder->addMissingAssociations($product)->shouldBeCalled();
        $product->getAssociationForTypeCode('xsell')->willReturn($xsellAssociation);

        $productRepository->findOneByIdentifier('not existing product')->willReturn(null);

        $this->shouldThrow(
            InvalidArgumentException::expected(
                'associations',
                'existing product identifier',
                'setter',
                'association',
                'not existing product'
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                ['xsell' => ['groups' => [], 'products' => ['not existing product']]]
            ]
        );
    }

    function it_fails_if_one_of_the_associated_group_does_not_exist(
        $productBuilder,
        $groupRepository,
        ProductInterface $product,
        AssociationInterface $xsellAssociation,
        AssociationTypeInterface $associationType
    ) {
        $xsellAssociation->getAssociationType()->willReturn($associationType);
        $xsellAssociation->getGroups()->willReturn(new ArrayCollection([]));
        $xsellAssociation->getProducts()->willReturn(new ArrayCollection([]));
        $product->getAssociations()->willReturn(new ArrayCollection([$xsellAssociation->getWrappedObject()]));
        $productBuilder->addMissingAssociations($product)->shouldBeCalled();
        $product->getAssociationForTypeCode('xsell')->willReturn($xsellAssociation);

        $groupRepository->findOneByIdentifier('not existing group')->willReturn(null);

        $this->shouldThrow(
            InvalidArgumentException::expected(
                'associations',
                'existing group code',
                'setter',
                'association',
                'not existing group'
            )
        )->during(
            'setFieldData',
            [
                $product,
                'associations',
                ['xsell' => ['groups' => ['not existing group'], 'products' => []]]
            ]
        );
    }

    function it_should_clear_concerned_associations(
        $productBuilder,
        ProductInterface $product,
        AssociationInterface $xsellAssociation,
        AssociationInterface $upsellAssociation,
        AssociationTypeInterface $upsellAssociationType,
        AssociationTypeInterface $xsellAssociationType,
        ArrayCollection $xsellGroups,
        ArrayCollection $xsellProducts,
        ArrayCollection $upsellGroups,
        ArrayCollection $upsellProducts
    ) {
        $xsellAssociationType->getCode()->willReturn('xsell');
        $xsellAssociation->getAssociationType()->willReturn($xsellAssociationType);
        $xsellAssociation->getGroups()->willReturn($xsellGroups);
        $xsellAssociation->getProducts()->willReturn($xsellProducts);

        $upsellAssociationType->getCode()->willReturn('upsell');
        $upsellAssociation->getAssociationType()->willReturn($upsellAssociationType);
        $upsellAssociation->getGroups()->willReturn($upsellGroups);
        $upsellAssociation->getProducts()->willReturn($upsellProducts);

        $product->getAssociations()->willReturn(
            new ArrayCollection([$xsellAssociation->getWrappedObject(), $upsellAssociation->getWrappedObject()])
        );

        $xsellProducts->clear()->shouldBeCalled();
        $xsellGroups->clear()->shouldNotBeCalled();
        $upsellProducts->clear()->shouldNotBeCalled();
        $upsellGroups->clear()->shouldBeCalled();

        $productBuilder->addMissingAssociations($product)->shouldBeCalled();
        $product->getAssociationForTypeCode('xsell')->willReturn($xsellAssociation);
        $product->getAssociationForTypeCode('upsell')->willReturn($upsellAssociation);

        $this->setFieldData(
            $product,
            'associations',
            [
                'xsell' => [
                    'products' => []
                ],
                'upsell' => [
                    'groups' => []
                ]
            ]
        );
    }
}
