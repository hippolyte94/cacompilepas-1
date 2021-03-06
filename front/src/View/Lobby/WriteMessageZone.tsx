import React, {ReactNode} from "react";
import '../../css/Message.css';
import Input from "../General/Input";

interface WriteMessageZoneProps {
    labelLobby: string,
    onEnter: (event: React.KeyboardEvent<HTMLDivElement>) => void,
}

class WriteMessageZone extends React.Component<WriteMessageZoneProps, any> {
    public render(): ReactNode {
        return (
            <div className={'col-lg-12 mt-lg-5 ml-lg-0 pl-lg-0 text-left'}
                onKeyDown={this.props.onEnter}
            >
                <Input
                    id={'message-input'}
                    inputType={'text'}
                    placeholder={'Ecrire un message sur #' + this.props.labelLobby}
                    checked={false}
                    className={'text-left l'}
                    onChange={(event: any) => console.log(event)}
                />
            </div>
        )
    }
}

export default WriteMessageZone;
