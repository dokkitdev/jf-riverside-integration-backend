{!!'<?xml version="1.0" ?>'!!}
<message>
    <Header>
        <Security username="{{$user_name}}" password="{{$password}}" />
    </Header>
    <Body>
        <Request request_type="varyjobextcont">
            <Parameters attribute="secret" attribute_value="{{$secret}}" />
            <Parameters attribute="job_no" attribute_value="{{$job_order_no}}" />

            @foreach($sor_elements as $index => $sor_element)
                <Parameters attribute="elementCode_{{$index + 1}}" attribute_value="{{$sor_element['element_code']}}" />
                <Parameters attribute="qty_{{$index + 1}}" attribute_value="{{$sor_element['qty']}}" />
            @endforeach
        </Request>
    </Body>
</message>