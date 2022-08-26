{!!'<?xml version="1.0" ?>'!!}
<message>
    <Header>
        <Security username="{{$user_name}}" password="{{$password}}" />
    </Header>
    <Body>
        <Request request_type="cancelapptextcont">
            <Parameters attribute="secret" attribute_value="{{$secret}}" />
            <Parameters attribute="job_no" attribute_value="{{$job_order_no}}" />
        </Request>
    </Body>
</message>