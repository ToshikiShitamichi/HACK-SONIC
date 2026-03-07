$(function () {
    const PREFECTURE_LIST = [
        "北海道",
        "青森県", "岩手県", "宮城県", "秋田県", "山形県", "福島県",
        "茨城県", "栃木県", "群馬県", "埼玉県", "千葉県", "東京都", "神奈川県",
        "新潟県", "富山県", "石川県", "福井県", "山梨県", "長野県",
        "岐阜県", "静岡県", "愛知県", "三重県",
        "滋賀県", "京都府", "大阪府", "兵庫県", "奈良県", "和歌山県",
        "鳥取県", "島根県", "岡山県", "広島県", "山口県",
        "徳島県", "香川県", "愛媛県", "高知県",
        "福岡県", "佐賀県", "長崎県", "熊本県", "大分県", "宮崎県", "鹿児島県",
        "沖縄県"
    ];

    $("#budgetSlider").slider({
        range: true,
        min: 0,
        max: 300000,
        step: 5000,
        values: [0, 100000],
        slide: function (event, ui) {
            updateBudgetDisplay(ui.values[0], ui.values[1]);
        },
        create: function () {
            const values = $(this).slider("values");
            updateBudgetDisplay(values[0], values[1]);
        }
    });

    function updateBudgetDisplay(min, max) {
        $("#budgetMin").val(min);
        $("#budgetMax").val(max);
        $("#budgetMinText").text(formatYen(min));
        $("#budgetMaxText").text(formatYen(max));
    }

    function formatYen(value) {
        return Number(value).toLocaleString("ja-JP") + "円";
    }

    $("#travelForm").on("submit", function (e) {
        const budgetMin = Number($("#budgetMin").val());
        const budgetMax = Number($("#budgetMax").val());

        if (budgetMin > budgetMax) {
            e.preventDefault();
            alert("予算の下限が上限を超えています。");
            return false;
        }
    });

    $("#getCurrentLocationButton").on("click", function () {
        const $message = $("#locationMessage");
        $message.text("現在地を取得中です...");

        if (!navigator.geolocation) {
            $message.text("このブラウザでは現在地取得に対応していません。");
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function (position) {
                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;

                reverseGeocode(latitude, longitude)
                    .done(function (data) {
                        const address = data.address || {};
                        const prefecture = address.province || "";

                        if (prefecture && PREFECTURE_LIST.includes(prefecture)) {
                            $("#departurePrefecture").val(prefecture);
                            $message.text("現在地から出発地を設定しました。");
                        } else {
                            $message.text("現在地から都道府県を特定できませんでした。");
                        }
                    })
                    .fail(function () {
                        $message.text("現在地の住所変換に失敗しました。");
                    });
            },
            function () {
                $message.text("現在地を取得できませんでした。");
            }
        );
    });

    function reverseGeocode(latitude, longitude) {
        return $.ajax({
            url: "https://nominatim.openstreetmap.org/reverse",
            method: "GET",
            dataType: "json",
            data: {
                format: "json",
                lat: latitude,
                lon: longitude,
                "accept-language": "ja"
            }
        });
    }
});