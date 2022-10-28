{!!'<?xml version="1.0" ?>'!!}
<message>
    <Header>
        <Security username="{{$user_name}}" password="{{$password}}" />
    </Header>
    <Body>
        <Request request_type="createstageextcont">
            <Parameters attribute="secret" attribute_value="{{$secret}}" />
            <Parameters attribute="job_no" attribute_value="{{$job_order_no}}" />
            <Parameters attribute="memo" attribute_value="{{$memo}}" />
            <Parameters attribute="stage" attribute_value="{{$stage}}" />
            <Parameters attribute="date" attribute_value="{{$date}}" />
            <Parameters attribute="time" attribute_value="{{$time}}" />
        </Request>
    </Body>
</message>