<?php

class AsrUtilTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var AsrUtil
     */
    private $util;

    /**
     * @var AsrSigningAlgorithm
     */
    private $algorithm;

    private $secretKey = 'AWS4wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';
    private $accessKeyId = 'AKIDEXAMPLE';
    private $baseCredentials = array('us-east-1', 'iam', 'aws4_request');
    private $host = 'iam.amazonaws.com';

    /**
     * @param $headerList
     * @param $headersToSign
     * @return array
     */
    public function callSignRequest($headerList, $headersToSign)
    {
        return AsrAuthHeader::create()
            ->useAlgorithm(AsrUtil::SHA256)
            ->useAmazonTime('20110909T233600Z')
            ->useCredentials($this->accessKeyId, $this->baseCredentials)
            ->useHeaders($this->host, $headerList, $headersToSign)
            ->useRequest('POST', '/', '', $this->payload())
            ->build($this->secretKey);
    }

    protected function setUp()
    {
        $this->util = new AsrUtil();
        $this->algorithm = new AsrSigningAlgorithm(AsrUtil::SHA256);
    }

    /**
     * @test
     */
    public function itShouldSignRequest()
    {
        $headersToSign = array('Content-Type');
        $headerList = $this->headers();
        $this->assertEquals($this->authorizationHeader(), $this->callSignRequest($headerList, $headersToSign));
    }

    /**
     * @test
     */
    public function itShouldAutomagicallyAddDateAndHostHeader()
    {
        $headerList = array('Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8');
        $headersToSign = array('Content-Type');
        $this->assertEquals($this->authorizationHeader(), $this->callSignRequest($headerList, $headersToSign));
    }

    /**
     * @test
     */
    public function itShouldOnlySignHeadersExplicitlySetToBeSigned()
    {
        $headerList = array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            'X-A-Header' => 'that/should/not/be/signed'
        );
        $headersToSign = array('Content-Type');
        $this->assertEquals($this->authorizationHeader(), $this->callSignRequest($headerList, $headersToSign));
    }

    /**
     * @test
     */
    public function itShouldGenerateCanonicalHash()
    {
        $headers = AsrHeaders::createFrom($this->headers(), array_keys($this->headers()));
        $request = new AsrRequest('POST', '/', '', $this->payload(), $headers);
        $result = $request->canonicalizeUsing($this->algorithm);
        $this->assertEquals('3511de7e95d28ecd39e9513b642aee07e54f4941150d8df8bf94b328ef7e55e2', $result);
    }

    /**
     * @test
     */
    public function itShouldCalculateSigningKey()
    {
        $credentials = new AsrCredentials('20120215TIRRELEVANT', $this->accessKeyId, $this->baseCredentials);
        $result = $credentials->generateSigningKeyUsing($this->algorithm, $this->secretKey);
        $this->assertEquals('f4780e2d9f65fa895f9c67b32ce1baf0b0d8a43505a000a1a9e090d414db404d', bin2hex($result));
    }

    /**
     * @test
     */
    public function itShouldParseAuthorizationHeader()
    {
        $headerList = $this->authorizationHeader();
        $actual = AsrAuthHeader::parse($headerList['Authorization']);
        $this->assertEquals('SHA256', $actual['algorithm']);
        $this->assertEquals('AKIDEXAMPLE/20110909/us-east-1/iam/aws4_request', $actual['credentials']);
        $this->assertEquals('content-type;host;x-amz-date', $actual['signed_headers']);
        $this->assertEquals('ced6826de92d2bdeed8f846f0bf508e8559e98e4b0199114b84c54174deb456c', $actual['signature']);
    }

    /**
     * @test
     */
    public function itShouldThrowExceptionIfDatesAreTooFarApart()
    {
        $validator = new AsrValidator();
        $actual = $validator->validateDates('20110909T233600Z', '20110909T232500Z', '20110909');
        $this->assertFalse($actual);
    }

    /**
     * @test
     */
    public function itShouldNotThrowExceptionsIfDatesAreAcceptable()
    {
        $validator = new AsrValidator();
        $actual = $validator->validateDates('20110909T233600Z', '20110909T233200Z', '20110909');
        $this->assertTrue($actual);
    }

    /**
     * @return array
     */
    private function headers()
    {
        return array(
            'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
            'Host' => $this->host,
            'X-Amz-Date' => '20110909T233600Z',
        );
    }

    /**
     * @return string
     */
    private function payload()
    {
        return 'Action=ListUsers&Version=2010-05-08';
    }

    /**
     * @return array
     */
    private function authorizationHeader()
    {
        return array(
            'Authorization' =>
                'AWS4-HMAC-SHA256 '.
                'Credential=AKIDEXAMPLE/20110909/us-east-1/iam/aws4_request, '.
                'SignedHeaders=content-type;host;x-amz-date, '.
                'Signature=ced6826de92d2bdeed8f846f0bf508e8559e98e4b0199114b84c54174deb456c',
            'X-Amz-Date'    => '20110909T233600Z',
        );
    }
}
