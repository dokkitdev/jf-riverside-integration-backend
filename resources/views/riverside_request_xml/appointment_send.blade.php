{!!'<?xml version="1.0" ?>'!!}
<message>
    <Header>
        <Security username="{{$user_name}}" password="{{$password}}" />
    </Header>
    <Body>
        <Request request_type="logapptextcont">
            <Parameters attribute="secret" attribute_value="{{$secret}}" />
            <Parameters attribute="job_no" attribute_value="{{$job_order_no}}" />
            <Parameters attribute="appointment_date" attribute_value="{{$schedule_date}}" />
            <Parameters attribute="appointment_time" attribute_value="{{$schedule_start_time}}" />
        </Request>
    </Body>
</message>