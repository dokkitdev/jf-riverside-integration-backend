{!!'<?xml version="1.0" ?>'!!}
<message>
    <Header>
        <Security username="{{$user_name}}" password="{{$password}}" />
    </Header>
    <Body>
        <Request request_type="creatediaryextcont">
            <Parameters attribute="secret" attribute_value="{{$secret}}" />
            <Parameters attribute="reference" attribute_value="{{$job_order_no}}" />
            <Parameters attribute="text" attribute_value="{{$description_notes}}" />
            <Parameters attribute="reference_type" attribute_value="RJ" />
            <Parameters attribute="entry_status" attribute_value="N" />
            <Parameters attribute="entry_type" attribute_value="MISC" />
            <Parameters attribute="entry_date" attribute_value="{{$entry_date}}" />
            <Parameters attribute="entry_time" attribute_value="{{$entry_time}}" />
        </Request>
    </Body>
</message>