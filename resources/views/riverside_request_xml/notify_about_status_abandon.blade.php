{!!'<?xml version="1.0" ?>'!!}
<message>
    <Header>
        <Security username="{{$user_name}}" password="{{$password}}" />
    </Header>
    <Body>
        <Request request_type="canceljobextcont">
            <Parameters attribute="secret" attribute_value="{{$secret}}" />
            <Parameters attribute="job_no" attribute_value="{{$job_order_no}}" />
            <Parameters attribute="abandon_reason" attribute_value="NWK" />
            <Parameters attribute="date_abandoned" attribute_value="{{$date}}" />
            <Parameters attribute="time_abandoned" attribute_value="{{$time}}" />
            <Parameters attribute="appointment_abandonment_reason" attribute_value="ASSIGNED" />
            <Parameters attribute="abandonment_description" attribute_value="No Work Required" />
        </Request>
    </Body>
</message>