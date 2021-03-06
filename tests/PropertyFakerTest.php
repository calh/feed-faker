<?php


use DI\ContainerBuilder;
use FeedFaker\Classes\MemberFaker;
use FeedFaker\Classes\OfficeFaker;
use FeedFaker\Classes\PropertyFaker;


class PropertyFakerTest extends PHPUnit_Framework_TestCase
{
    public $mls_id;
    public $member1;
    public $member2;
    public $container;

    public function setUp()
    {
        // need to have your Config/Settings file populated or these tests will fail
        $containerBuilder = new ContainerBuilder;
        $containerBuilder->addDefinitions(__DIR__ . '/settings_fixture.php');
        $this->container = $containerBuilder->build();

        $office = new OfficeFaker($this->container);
        $office = $office->generate();

        $member = new MemberFaker($this->container);
        $this->member1 = $member->generate($office);
        $this->member2 = $member->generate($office);
    }

    public function tearDown()
    {
        parent::tearDown(); // TODO: Change the autogenerated stub
    }

    public function testPropertyFaker()
    {
        $property = new PropertyFaker($this->container);
        $property_object = $property->generate($this->member1, $this->member2);

        $this->assertInstanceOf(\FeedFaker\Models\Property::class, $property_object);
    }

    public function testOverrides()
    {
        $property = new PropertyFaker($this->container);
        $property_object = $property->generate($this->member1, $this->member2, ['AssociationName2' => 'Association Two Name']);

        $this->assertSame('Association Two Name', $property_object->getAssociationName2());
    }

    public function testPropertyStatusAndTypeRequirements()
    {
        $lease_amount_freqs = [
            'Month',
            'Week',
            'Year',
        ];

        $lease_terms = [
            '12 Months',
            '24 Months',
            '6 Months',
            'Month To Month',
            'Negotiable',
            'Weekly',
            'None',
            'Other',
            'Renewal Option',
            'Short Term Lease'
        ];

        for ($i = 0; $i <= 10; $i++) {
            $property = new PropertyFaker($this->container);
            $property_object = $property->generate($this->member1, $this->member2);

            $status = $property_object->getMlsStatus();
            $type = $property_object->getPropertyType();

            // check statuses
            switch ($status) {
                case 'Active':
                    break;
                case 'Active Under Contract':
                case 'Pending':
                    $this->assertNotEmpty($property_object->getPurchaseContractDate());
                    $this->assertNotEmpty($property_object->getPendingTimestamp());
                    $this->assertNotEmpty($property_object->getBuyerAgentKey());
                    break;
                case 'Backup':
                    break;
                case 'Withdrawn':
                    $this->assertNotEmpty($property_object->getWithdrawnDate());
                    break;
                case 'Cancelled':
                    $this->assertNotEmpty($property_object->getCancelationDate());
                    break;
                case 'Expired':
                    break;
                case 'Closed':
                    $this->assertNotEmpty($property_object->getPurchaseContractDate());
                    $this->assertNotEmpty($property_object->getPendingTimestamp());
                    $this->assertNotEmpty($property_object->getBuyerAgentKey());
                    $this->assertNotEmpty($property_object->getCloseDate());
                    $this->assertNotEmpty($property_object->getClosePrice());
                    break;
            }

            // check property type
            switch ($type) {
                case 'BUS':
                    $this->assertNotEmpty($property_object->getBusinessName());
                    break;
                case 'COM':
                    break;
                case 'LND':
                    break;
                case 'RES':
                    $this->assertNotEmpty($property_object->getBedroomsTotal());
                    $this->assertNotEmpty($property_object->getBathroomsTotalInteger());
                    break;
                case 'RIN':
                    $this->assertNotEmpty($property_object->getNumberOfSeparateElectricMeters());
                    $this->assertNotEmpty($property_object->getNumberOfSeparateGasMeters());
                    $this->assertNotEmpty($property_object->getNumberOfSeparateWaterMeters());
                    break;
                case 'RNT':
                    break;
                case 'LSE':
                    $this->assertTrue(($property_object->getLeaseAmount() > 350 and $property_object->getLeaseAmount() < 3000));
                    $this->assertTrue(in_array($property_object->getLeaseAmountFrequency(), $lease_amount_freqs));
                    $this->assertTrue($property_object->getLeaseConsideredYN() == 'Y');
                    $this->assertTrue(in_array($property_object->getLeaseTerm(), $lease_terms));
                    break;
            }

            // check bathrooms
            if (!in_array($type, ['LND', 'BUS', 'COM'])) {
                // we should have baths for any of these
                $this->assertNotEmpty($property_object->getBathroomsTotalInteger());

                $full = $property_object->getBathroomsFull();
                $three_quarter = $property_object->getBathroomsThreeQuarter();
                $half = $property_object->getBathroomsHalf();
                $one_quarter = $property_object->getBathroomsOneQuarter();
                $partial = $property_object->getBathroomsPartial();

                $this->assertSame($full+$three_quarter+$half+$one_quarter+$partial, $property_object->getBathroomsTotalInteger());

                if ($property_object->getBathroomsPartial()) {
                    // if we have partial set, we shouldn't have the quarter/half set
                    $this->assertEmpty($property_object->getBathroomsThreeQuarter());
                    $this->assertEmpty($property_object->getBathroomsHalf());
                    $this->assertEmpty($property_object->getBathroomsOneQuarter());
                }
            }
        }
     }

    public function testItLimitsFields()
    {
        $property = new PropertyFaker($this->container);
        $property_object = $property->generate($this->member1, $this->member2);

        $limited = [
            "ListingService",
            "ListingAgreement",
            "LockboxLocation",
            "Exclusions",
            "Concessions",
            "Country",
            "PropertySubType",
            "AssociationFeeFrequency",
            "LotSizeSource",
            "LotSizeUnits",
            "OccupantType",
            "LeaseTerm",
            "Furnished",
            "BusinessType",
            "OwnershipType",
            "LeaseAmountFrequency",
            "HoursDaysofOperation",
            "Electric",
            "Gas",
            "Fencing",
            "OtherParking",
            "YearBuiltSource",
            "ArchitecturalStyle",
            "DirectionFaces",
            "Appliances",
        ];

        foreach ($limited as $limit) {
            $method = 'get'.$limit;
            $value = $property_object->$method();

            $this->assertFalse(is_array($value)); // should not be an array
            $this->assertSame(1, count(explode(',', $value)));
        }
    }
}
