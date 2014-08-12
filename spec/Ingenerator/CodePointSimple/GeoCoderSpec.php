<?php
/**
 * Defines GeoCoderSpec - specifications for Ingenerator\CodePointSimple\GeoCoder
 *
 * @author    Matthias Gisder <matthias@ingenerator.com>
 * @copyright  2014 inGenerator Ltd
 * @licence    BSD
 */

namespace spec\Ingenerator\CodePointSimple;

use spec\ObjectBehavior;
use Prophecy\Argument;

use \Ingenerator\CodePointSimple\Helper\FileReader;
/**
 *
 * @see Ingenerator\CodePointSimple\GeoCoder
 */
class GeoCoderSpec extends ObjectBehavior
{
    const DB_BASE_DIR = './output';

    /**
     * Use $this->subject to get proper type hinting for the subject class
     * @var \Ingenerator\CodePointSimple\GeoCoder
     */
	protected $subject;

    /**
     * @param \Ingenerator\CodePointSimple\Helper\FileReader $file_reader
     */
    function let($file_reader)
    {
        $file_reader->json_read(Argument::type('string'))->willReturn();
        $file_reader->json_read(self::DB_BASE_DIR.'/EH/4/centre.json')->willReturn(array('EH4', 'centre'));
        $file_reader->json_read(self::DB_BASE_DIR.'/EH/41/centre.json')->willReturn(array('EH41', 'centre'));

        $file_reader->json_read(self::DB_BASE_DIR.'/EH/41/3.json')->willReturn(array('AA' => array('EH41', '3')));
        $file_reader->json_read(self::DB_BASE_DIR.'/EH/4/1.json')->willReturn(array('AA' => array('EH4', '1')));

        $this->init($file_reader, self::DB_BASE_DIR);
    }

	function it_is_initializable()
    {
		$this->subject->shouldHaveType('Ingenerator\CodePointSimple\GeoCoder');
	}

    function it_encodes_partial_postcodes()
    {
        $this->subject->geocode('EH4')->shouldBe(array('lat' => 'EH4', 'lon' => 'centre', 'exact' => FALSE));
        $this->subject->geocode('EH4 1')->shouldBe(array('lat' => 'EH4', 'lon' => 'centre', 'exact' => FALSE));
        $this->subject->geocode('EH41 3')->shouldBe(array('lat' => 'EH41', 'lon' => 'centre', 'exact' => FALSE));
  }

    function it_fails_on_invalid_partial_postcodes()
    {
        $this->subject->geocode('EH4 1A')->shouldBe(NULL);
        $this->subject->geocode('EH41 3A')->shouldBe(NULL);
    }

    /**
     * @param \Ingenerator\CodePointSimple\Helper\FileReader $file_reader
     */
    function it_fails_on_postcodes_in_wrong_format($file_reader)
    {
        $file_reader->json_read(self::DB_BASE_DIR.'/EH/41/centre.json')->willReturn(array('EH41', 'centre'));
        $this->subject->geocode('EH4 13AA')->shouldBe(NULL);
        $this->subject->geocode('EH413A')->shouldBe(NULL);
    }

    function it_encodes_complete_postcodes()
    {
        $this->subject->geocode('EH4 1AA')->shouldBe(array('lat' => 'EH4', 'lon' => '1', 'exact' => TRUE));
        $this->subject->geocode('EH41 3AA')->shouldBe(array('lat' => 'EH41', 'lon' => '3', 'exact' => TRUE));
    }

    /**
     * @param \Ingenerator\CodePointSimple\Helper\FileReader $file_reader
     */
    function it_handles_gratuitous_whitespace_in_partial_postcodes($file_reader)
    {
        $file_reader->json_read(self::DB_BASE_DIR.'/EH/41/centre.json')->willReturn(array('EH41', 'centre'));
        $result=$this->subject->geocode('EH41 ');
        $this->subject->geocode('EH4 ')->shouldBe(array('lat' => 'EH4', 'lon' => 'centre', 'exact' => FALSE));
        $this->subject->geocode('EH41 ')->shouldBe(array('lat' => 'EH41', 'lon' => 'centre', 'exact' => FALSE));
    }

    /**
     * @param \Ingenerator\CodePointSimple\Helper\FileReader $file_reader
     */
    function it_handles_missing_whitespace_in_partial_postcodes($file_reader)
    {
        $file_reader->json_read(self::DB_BASE_DIR.'/EH/41/centre.json')->willReturn(array('EH41', 'centre'));
        $this->subject->geocode('EH41')->shouldBe(array('lat' => 'EH41', 'lon' => 'centre', 'exact' => FALSE));
        $this->subject->geocode('EH413')->shouldBe(array('lat' => 'EH41', 'lon' => 'centre', 'exact' => FALSE));
        $this->subject->geocode('EH413AA')->shouldBe(array('lat' => 'EH41', 'lon' => '3', 'exact' => TRUE));
    }

    /**
     * @param \Ingenerator\CodePointSimple\Helper\FileReader $file_reader
     */
    function it_handles_missing_whitespace_in_complete_postcodes($file_reader)
    {
        $file_reader->json_read(self::DB_BASE_DIR.'/EH/41/centre.json')->willReturn(array('EH41', 'centre'));
        $result = $this->subject->geocode('EH41AA');
        $this->subject->geocode('EH41AA')->shouldBe(array('lat' => 'EH41', 'lon' => 'centre', 'exact' => FALSE));
    }

    /**
     * @param \Ingenerator\CodePointSimple\Helper\FileReader $file_reader
     */
    function it_returns_centre_on_invalid_postcode($file_reader)
    {
        $file_reader->json_read(self::DB_BASE_DIR.'/EH/41/centre.json')->willReturn(array('EH41', 'centre'));

        $this->subject->geocode('EH41 1NV')->shouldBe(array('lat' => 'EH41', 'lon' => 'centre', 'exact' => FALSE));
    }

}
